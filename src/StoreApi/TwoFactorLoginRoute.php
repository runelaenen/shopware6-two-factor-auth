<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\StoreApi;

use Shopware\Core\Checkout\Customer\CustomerEntity;
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
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;
use Tinect\TwoFactorAuth\Service\CustomerMethodResolver;
use Tinect\TwoFactorAuth\Service\EmailCodeService;
use Tinect\TwoFactorAuth\Service\SecretStorage;
use Tinect\TwoFactorAuth\Service\TotpVerifier;
use Tinect\TwoFactorAuth\TwoFactorAuthException;

/**
 * Store API clients get no storefront session to park a pending verification in, so the code has to come with
 * the login itself. App codes are sent right away. Customers who chose e-mail codes first get a code mailed and a
 * challenge back, then send the login again with both. The storefront keeps its own verification page, see
 * CustomerLoginSubscriber.
 */
#[AsDecorator(decorates: LoginRoute::class)]
class TwoFactorLoginRoute extends AbstractLoginRoute
{
    public const OTP_PARAMETER = 'tinect2faOtp';

    public const CHALLENGE_PARAMETER = 'tinect2faChallenge';

    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractLoginRoute $decorated,
        private readonly AccountService $accountService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly SecretStorage $secretStorage,
        private readonly CustomerMethodResolver $methodResolver,
        private readonly TotpVerifier $totpVerifier,
        private readonly EmailCodeService $emailCodeService,
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

        $method = $this->methodResolver->resolve($customer->getId(), $context->getSalesChannelId());

        // Enforced e-mail codes stay a storefront feature: existing Store API clients don't know the extra step.
        if ($method === CustomerMethodResolver::METHOD_EMAIL && !$this->secretStorage->hasCustomerEmail($customer->getId())) {
            $method = null;
        }

        if ($method === null) {
            return $this->decorated->login($data, $context);
        }

        $otp = (string) $data->get(self::OTP_PARAMETER);
        $challenge = (string) $data->get(self::CHALLENGE_PARAMETER);

        if ($method === CustomerMethodResolver::METHOD_EMAIL) {
            if ($otp === '' || $challenge === '') {
                $this->sendEmailChallenge($customer, $context);
            }
        } elseif ($otp === '') {
            throw TwoFactorAuthException::otpRequired();
        }

        $rateLimitKey = 'tinect-2fa-' . $customer->getId();

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $rateLimitKey);
        } catch (RateLimitExceededException $exception) {
            throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
        }

        $verified = $method === CustomerMethodResolver::METHOD_EMAIL
            ? $this->emailCodeService->verifyChallenge($challenge, $customer->getId(), $otp)
            : $this->totpVerifier->verifyCustomer($customer->getId(), $otp);

        if (!$verified) {
            throw TwoFactorAuthException::otpInvalid();
        }

        $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $rateLimitKey);

        return $this->decorated->login($data, $context);
    }

    private function sendEmailChallenge(CustomerEntity $customer, SalesChannelContext $context): never
    {
        try {
            $challenge = $this->emailCodeService->sendChallenge($customer, $context);
        } catch (RateLimitExceededException $exception) {
            throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
        }

        if ($challenge === null) {
            throw TwoFactorAuthException::emailCodeNotSent();
        }

        throw TwoFactorAuthException::emailCodeRequired($challenge, EmailCodeService::TTL_SECONDS);
    }
}
