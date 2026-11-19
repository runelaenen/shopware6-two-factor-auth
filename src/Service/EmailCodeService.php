<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Sends one-time login codes by e-mail. Only a hash of the pending code is kept, in the customer's session.
 */
readonly class EmailCodeService
{
    public const MAIL_TEMPLATE_TYPE = 'tinect_2fa_email_code';

    public const TTL_SECONDS = 600;

    public const RESEND_COOLDOWN_SECONDS = 30;

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
    ) {
    }

    /**
     * @throws RateLimitExceededException when too many codes were sent to this customer recently
     */
    public function send(SessionInterface $session, CustomerEntity $customer, SalesChannelContext $context): bool
    {
        // Knowing the password is enough to trigger a mail, so the mails per customer are throttled across sessions.
        $this->rateLimiter->ensureAccepted(RateLimiter::RESET_PASSWORD, $this->getRateLimitKey($customer->getId()));

        $mailTemplate = $this->getMailTemplate($context);
        if ($mailTemplate === null) {
            $this->logger->error('Two-factor login code not sent: no mail template of type "{type}" found.', [
                'type' => self::MAIL_TEMPLATE_TYPE,
            ]);

            return false;
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

            return false;
        }

        if ($mail === null) {
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
