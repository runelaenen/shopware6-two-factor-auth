<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Sends one-time login codes by e-mail. Only a hash of the pending code is kept: in the customer's session for the
 * storefront, in a challenge row for Store API logins, which have no session.
 */
readonly class EmailCodeService
{
    public const MAIL_TEMPLATE_TYPE = 'tinect_2fa_email_code';

    public const TTL_SECONDS = 600;

    public const RESEND_COOLDOWN_SECONDS = 30;

    public const CHALLENGE_TABLE = 'rl_2fa_customer_challenge';

    private const MAX_ATTEMPTS = 5;

    private const SESSION_KEY = 'TINECT_2FA_EMAIL_CODE';

    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        #[Autowire(service: 'Shopware\Core\Content\Mail\Service\MailService')]
        private AbstractMailService $mailService,
        #[Autowire(service: 'mail_template.repository')]
        private EntityRepository $mailTemplateRepository,
        private RateLimiter $rateLimiter,
        private LoggerInterface $logger,
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private Connection $connection,
    ) {
    }

    /**
     * @throws RateLimitExceededException when too many codes were sent to this customer recently
     */
    public function send(SessionInterface $session, CustomerEntity $customer, SalesChannelContext $context): bool
    {
        $code = $this->mailCode($customer, $context);
        if ($code === null) {
            return false;
        }

        $session->set(self::SESSION_KEY, [
            'customerId' => $customer->getId(),
            'hash' => $this->hash($customer->getId(), $code),
            'expiresAt' => time() + self::TTL_SECONDS,
            'sentAt' => time(),
            'attempts' => 0,
        ]);

        return true;
    }

    /**
     * Sends a code for a login without session. The returned challenge has to come back together with the code.
     * A new challenge replaces the older ones of the customer.
     *
     * @throws RateLimitExceededException when too many codes were sent to this customer recently
     */
    public function sendChallenge(CustomerEntity $customer, SalesChannelContext $context): ?string
    {
        $code = $this->mailCode($customer, $context);
        if ($code === null) {
            return null;
        }

        $challenge = Random::getAlphanumericString(48);
        $customerId = Uuid::fromHexToBytes($customer->getId());

        $this->connection->executeStatement(
            \sprintf('DELETE FROM `%s` WHERE `customer_id` = :customerId OR `expires_at` < :now', self::CHALLENGE_TABLE),
            ['customerId' => $customerId, 'now' => $this->formatTime(time())]
        );

        $this->connection->insert(self::CHALLENGE_TABLE, [
            'id' => $this->challengeId($challenge),
            'customer_id' => $customerId,
            'code_hash' => $this->hash($customer->getId(), $code),
            'attempts' => 0,
            'expires_at' => $this->formatTime(time() + self::TTL_SECONDS),
            'created_at' => $this->formatTime(time()),
        ]);

        return $challenge;
    }

    public function verifyChallenge(string $challenge, string $customerId, string $code): bool
    {
        if ($challenge === '' || !Uuid::isValid($customerId)) {
            return false;
        }

        $params = [
            'id' => $this->challengeId($challenge),
            'customerId' => Uuid::fromHexToBytes($customerId),
            'now' => $this->formatTime(time()),
            'maxAttempts' => self::MAX_ATTEMPTS,
        ];

        // Counting the attempt first in one conditional UPDATE keeps parallel guesses within the limit.
        $counted = $this->connection->executeStatement(
            \sprintf(
                'UPDATE `%s` SET `attempts` = `attempts` + 1
                WHERE `id` = :id AND `customer_id` = :customerId AND `expires_at` >= :now AND `attempts` < :maxAttempts',
                self::CHALLENGE_TABLE
            ),
            $params
        );

        if ($counted !== 1) {
            return false;
        }

        $hash = $this->connection->fetchOne(
            \sprintf('SELECT `code_hash` FROM `%s` WHERE `id` = :id', self::CHALLENGE_TABLE),
            ['id' => $params['id']]
        );

        if (!\is_string($hash) || !hash_equals($hash, $this->hash($customerId, preg_replace('/\s+/', '', $code) ?? ''))) {
            return false;
        }

        // Deleting decides between two parallel requests with the right code: only one of them removes the row.
        $deleted = $this->connection->executeStatement(
            \sprintf('DELETE FROM `%s` WHERE `id` = :id', self::CHALLENGE_TABLE),
            ['id' => $params['id']]
        );

        if ($deleted !== 1) {
            return false;
        }

        $this->rateLimiter->reset(RateLimiter::RESET_PASSWORD, $this->getRateLimitKey($customerId));

        return true;
    }

    public function hasPendingCode(SessionInterface $session, string $customerId): bool
    {
        return $this->getPending($session, $customerId) !== null;
    }

    public function getResendWaitTime(SessionInterface $session, string $customerId): int
    {
        $pending = $this->getPending($session, $customerId);
        if ($pending === null) {
            return 0;
        }

        return max(0, $pending['sentAt'] + self::RESEND_COOLDOWN_SECONDS - time());
    }

    public function verify(SessionInterface $session, string $customerId, string $code): bool
    {
        $pending = $this->getPending($session, $customerId);
        if ($pending === null) {
            return false;
        }

        if (!hash_equals($pending['hash'], $this->hash($customerId, preg_replace('/\s+/', '', $code) ?? ''))) {
            ++$pending['attempts'];
            $session->set(self::SESSION_KEY, $pending);

            return false;
        }

        $this->clear($session);
        $this->rateLimiter->reset(RateLimiter::RESET_PASSWORD, $this->getRateLimitKey($customerId));

        return true;
    }

    public function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }

    /**
     * @return array{customerId: string, hash: string, expiresAt: int, sentAt: int, attempts: int}|null
     */
    private function getPending(SessionInterface $session, string $customerId): ?array
    {
        $pending = $session->get(self::SESSION_KEY);
        if (!\is_array($pending)
            || ($pending['customerId'] ?? null) !== $customerId
            || !\is_string($pending['hash'] ?? null)
            || !\is_int($pending['expiresAt'] ?? null)
            || !\is_int($pending['sentAt'] ?? null)
            || !\is_int($pending['attempts'] ?? null)
            || $pending['expiresAt'] < time()
            || $pending['attempts'] >= self::MAX_ATTEMPTS
        ) {
            return null;
        }

        /** @var array{customerId: string, hash: string, expiresAt: int, sentAt: int, attempts: int} $pending */
        return $pending;
    }

    /**
     * @throws RateLimitExceededException
     */
    private function mailCode(CustomerEntity $customer, SalesChannelContext $context): ?string
    {
        // Knowing the password is enough to trigger a mail, so the mails per customer are throttled across sessions.
        $this->rateLimiter->ensureAccepted(RateLimiter::RESET_PASSWORD, $this->getRateLimitKey($customer->getId()));

        $mailTemplate = $this->getMailTemplate($context);
        if ($mailTemplate === null) {
            $this->logger->error('Two-factor login code not sent: no mail template of type "{type}" found.', [
                'type' => self::MAIL_TEMPLATE_TYPE,
            ]);

            return null;
        }

        $code = \sprintf('%06d', random_int(0, 999999));

        $data = [
            'recipients' => [$customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName()],
            'senderName' => $mailTemplate->getTranslation('senderName'),
            'salesChannelId' => $context->getSalesChannelId(),
            'templateId' => $mailTemplate->getId(),
            'customFields' => $mailTemplate->getCustomFields(),
            'contentHtml' => $mailTemplate->getTranslation('contentHtml'),
            'contentPlain' => $mailTemplate->getTranslation('contentPlain'),
            'subject' => $mailTemplate->getTranslation('subject'),
            'mediaIds' => [],
        ];

        try {
            $mail = $this->mailService->send($data, $context->getContext(), [
                'customer' => $customer,
                'code' => $code,
                'expiresInMinutes' => intdiv(self::TTL_SECONDS, 60),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Two-factor login code not sent: {message}', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return null;
        }

        return $mail === null ? null : $code;
    }

    /**
     * Only a hash of the challenge is stored, so a database dump holds no usable challenge.
     */
    private function challengeId(string $challenge): string
    {
        return hash('sha256', $challenge, true);
    }

    private function formatTime(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

        private function getMailTemplate(SalesChannelContext $context): ?MailTemplateEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('mailTemplateType.technicalName', self::MAIL_TEMPLATE_TYPE))
            ->addSorting(new FieldSorting('systemDefault', FieldSorting::DESCENDING))
            ->setLimit(1);

        // @phpstan-ignore method.deprecatedClass (EntitySearchResult only changes its class hierarchy in 6.8, getEntities() stays)
        return $this->mailTemplateRepository->search($criteria, $context->getContext())->getEntities()->first();
    }

    private function hash(string $customerId, string $code): string
    {
        return hash_hmac('sha256', 'tinect-2fa-email|' . $customerId . '|' . $code, $this->secret);
    }

    private function getRateLimitKey(string $customerId): string
    {
        return 'tinect-2fa-mail-' . $customerId;
    }
}
