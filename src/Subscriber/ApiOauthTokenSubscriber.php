<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Subscriber;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Tinect\TwoFactorAuth\Service\SecretStorage;
use Tinect\TwoFactorAuth\Service\TotpVerifier;
use Tinect\TwoFactorAuth\Service\UserVerificationTicketService;

#[AutoconfigureTag(name: 'kernel.event_subscriber')]
readonly class ApiOauthTokenSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        #[Autowire(service: 'user.repository')]
        private EntityRepository $userRepository,
        private TotpVerifier $totpVerifier,
        private UserVerificationTicketService $ticketService,
        private RateLimiter $rateLimiter,
        private SecretStorage $secretStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'api.oauth.token.response' => 'onApiOauthTokenResponse',
        ];
    }

    /**
     * @throws OAuthServerException
     */
    public function onApiOauthTokenResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== 'api.oauth.token'
            || $event->getResponse()->getStatusCode() !== 200
            || $request->request->getString('grant_type') !== 'password'
        ) {
            return;
        }

        // @phpstan-ignore method.deprecatedClass (EntitySearchResult only changes its class hierarchy in 6.8, getEntities() stays)
        $user = $this->userRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('username', $request->request->getString('username'))),
            Context::createCLIContext()
        )->getEntities()->first();

        $secret = $user instanceof UserEntity ? $this->secretStorage->getUserSecret($user->getId()) : null;
        if (!$user instanceof UserEntity || $secret === null) {
            return;
        }

        $scopes = explode(' ', $request->request->getString('scope'));
        $ticket = $request->request->getString('tinect_2fa_ticket');
        if (\in_array(UserVerifiedScope::IDENTIFIER, $scopes, true)
            && $ticket !== ''
            && $this->ticketService->isValid($ticket, $user->getId())
        ) {
            return;
        }

        $otp = $request->request->getString('tinect_2fa_otp');
        if ($otp === '') {
            throw new OAuthServerException('This user needs an extra OTP', 1010, 'request-otp', 401, 'request-otp');
        }

        $rateLimitKey = 'tinect-2fa-' . $user->getId();

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, $rateLimitKey);
        } catch (RateLimitExceededException $exception) {
            throw ApiException::notificationThrottled($exception->getWaitTime(), $exception);
        }

        if (!$this->totpVerifier->verifyUser($user->getId(), $otp)) {
            throw new OAuthServerException('Wrong OTP', 1011, 'wrong-otp', 401);
        }

        $this->rateLimiter->reset(RateLimiter::OAUTH, $rateLimitKey);
    }
}
