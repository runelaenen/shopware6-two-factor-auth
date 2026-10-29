<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\StoreApi;

use RuneLaenen\TwoFactorAuth\Service\SecretStorage;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordService;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use RuneLaenen\TwoFactorAuth\TwoFactorAuthException;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Store API clients get no storefront session to park a pending verification in, so the code has to come with
 * the login itself. The storefront keeps its own verification page, see CustomerLoginSubscriber.
 */
#[AsDecorator(decorates: LoginRoute::class)]
class TwoFactorLoginRoute extends AbstractLoginRoute
{
    public const OTP_PARAMETER = 'rl2faOtp';

    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractLoginRoute $decorated,
        private readonly AccountService $accountService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly SecretStorage $secretStorage,
        #[Autowire(service: TimebasedOneTimePasswordService::class)]
        private readonly TimebasedOneTimePasswordServiceInterface $totpService,
    ) {
    }

    public function getDecorated(): AbstractLoginRoute
    {
        return $this->decorated;
    }

    public function login(#[\SensitiveParameter] RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        $request = $this->requestStack->getMainRequest();
        $routeScope = (array) $request?->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        if ($request === null || \in_array(StorefrontRouteScope::ID, $routeScope, true)) {
            return $this->decorated->login($data, $context);
        }

        EmailIdnConverter::encodeDataBag($data);
        $email = (string) $data->get('email', $data->get('username'));

        // The password is checked here before core does, so it needs core's login throttle too.
        try {
            $this->rateLimiter->ensureAccepted(
                RateLimiter::LOGIN_ROUTE,
                strtolower($email) . '-' . $request->getClientIp()
            );
        } catch (RateLimitExceededException $exception) {
            throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
        }

        $customer = $this->accountService->getCustomerByLogin($email, (string) $data->get('password'), $context);

        $secret = $this->secretStorage->getCustomerSecret($customer->getId());
        if ($secret === null) {
            return $this->decorated->login($data, $context);
        }

        $otp = (string) $data->get(self::OTP_PARAMETER);
        if ($otp === '') {
            throw TwoFactorAuthException::otpRequired();
        }

        $rateLimitKey = 'rl-2fa-' . $customer->getId();

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $rateLimitKey);
        } catch (RateLimitExceededException $exception) {
            throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
        }

        try {
            $verified = $this->totpService->verifyCode($secret, $otp);
        } catch (\Exception) {
            $verified = false;
        }

        if (!$verified) {
            throw TwoFactorAuthException::otpInvalid();
        }

        $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $rateLimitKey);

        return $this->decorated->login($data, $context);
    }
}
