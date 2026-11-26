<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tinect\TwoFactorAuth\Event\StorefrontTwoFactorAuthEvent;
use Tinect\TwoFactorAuth\Event\StorefrontTwoFactorCancelEvent;
use Tinect\TwoFactorAuth\Service\ConfigurationService;
use Tinect\TwoFactorAuth\Service\CustomerMethodResolver;
use Tinect\TwoFactorAuth\Service\EmailCodeService;
use Tinect\TwoFactorAuth\Service\TotpVerifier;
use Tinect\TwoFactorAuth\Service\TrustedDeviceService;
use Tinect\TwoFactorAuth\Subscriber\CustomerLoginSubscriber;

#[AutoconfigureTag(name: 'controller.service_arguments')]
#[Route(defaults: ['_routeScope' => ['storefront']])]
class StorefrontTwoFactorAuthController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $dispatcher,
        #[Autowire(service: 'Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute')]
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly RateLimiter $rateLimiter,
        private readonly TotpVerifier $totpVerifier,
        private readonly CustomerMethodResolver $methodResolver,
        private readonly EmailCodeService $emailCodeService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly ConfigurationService $configurationService,
    ) {
    }

    #[Route(path: '/tinect-2fa/verification', name: 'frontend.tinect2fa.verification', methods: ['GET', 'POST'])]
    public function verification(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            return $this->redirectTo($request, 'frontend.account.login.page');
        }

        $session = $request->getSession();
        if (!$session->has(CustomerLoginSubscriber::SESSION_NAME)) {
            return $this->redirectTo($request, 'frontend.account.home.page');
        }

        $method = $this->methodResolver->resolve($customer->getId(), $context->getSalesChannelId());
        if ($method === null) {
            return $this->finishVerification($request, $context, false);
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            $rateLimitKey = 'tinect-2fa-' . $customer->getId();

            try {
                $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $rateLimitKey);

                if ($this->verifyCode($method, $request, $customer)) {
                    $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $rateLimitKey);

                    return $this->finishVerification($request, $context, $request->request->getBoolean('trustDevice'));
                }

                if ($method === CustomerMethodResolver::METHOD_EMAIL
                    && !$this->emailCodeService->hasPendingCode($session, $customer->getId())
                ) {
                    $this->addFlash(self::DANGER, $this->trans('tinect-2fa.login.email-code-expired'));
                } else {
                    $this->addFlash(self::DANGER, $this->trans('tinect-2fa.account.error.incorrect-code'));
                }
            } catch (RateLimitExceededException $exception) {
                $this->addFlash(self::DANGER, $this->trans('account.loginThrottled', ['%seconds%' => $exception->getWaitTime()]));
            }
        } elseif ($method === CustomerMethodResolver::METHOD_EMAIL
            && !$this->emailCodeService->hasPendingCode($session, $customer->getId())
        ) {
            $this->sendEmailCode($request, $customer, $context);
        }

        return $this->renderStorefront('@TinectTwoFactorAuth/storefront/page/2fa/verification.html.twig', [
            'redirect' => $request->query->all(),
            'method' => $method,
            'maskedEmail' => self::maskEmail($customer->getEmail()),
            'trustedDeviceDays' => $this->configurationService->getStorefrontTrustedDeviceDays($context->getSalesChannelId()),
        ]);
    }

    #[Route(path: '/tinect-2fa/verification/resend', name: 'frontend.tinect2fa.verification.resend', methods: ['POST'])]
    public function resendEmailCode(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            return $this->redirectTo($request, 'frontend.account.login.page');
        }

        $session = $request->getSession();
        if ($session->has(CustomerLoginSubscriber::SESSION_NAME)
            && $this->methodResolver->resolve($customer->getId(), $context->getSalesChannelId()) === CustomerMethodResolver::METHOD_EMAIL
        ) {
            $waitTime = $this->emailCodeService->getResendWaitTime($session, $customer->getId());
            if ($waitTime > 0) {
                $this->addFlash(self::DANGER, $this->trans('tinect-2fa.login.email-throttled', ['%seconds%' => $waitTime]));
            } elseif ($this->sendEmailCode($request, $customer, $context)) {
                $this->addFlash(self::SUCCESS, $this->trans('tinect-2fa.login.email-resent'));
            }
        }

        return $this->redirectToRoute('frontend.tinect2fa.verification', $request->query->all());
    }

    #[Route(path: '/tinect-2fa/verification/cancel', name: 'frontend.tinect2fa.verification.cancel', methods: ['GET'])]
    public function cancelVerification(Request $request, SalesChannelContext $context, RequestDataBag $dataBag): Response
    {
        if ($context->getCustomer() !== null) {
            $this->logoutRoute->logout($context, $dataBag);
        }
        $this->dispatcher->dispatch(new StorefrontTwoFactorCancelEvent($context));

        return $this->redirectTo($request, 'frontend.account.login.page');
    }

    private function verifyCode(string $method, Request $request, CustomerEntity $customer): bool
    {
        $code = $request->request->getString('otpCode');

        if ($method === CustomerMethodResolver::METHOD_EMAIL) {
            return $this->emailCodeService->verify($request->getSession(), $customer->getId(), $code);
        }

        return $this->totpVerifier->verifyCustomer($customer->getId(), $code);
    }

    private function finishVerification(Request $request, SalesChannelContext $context, bool $trustDevice): Response
    {
        $targetUrl = $request->getSession()->get(CustomerLoginSubscriber::SESSION_TARGET_URL);
        $this->dispatcher->dispatch(new StorefrontTwoFactorAuthEvent($context));

        $response = \is_string($targetUrl) && CustomerLoginSubscriber::isLocalPath($targetUrl)
            ? new RedirectResponse($targetUrl)
            : $this->redirectTo($request, 'frontend.account.home.page');

        $customer = $context->getCustomer();
        $days = $this->configurationService->getStorefrontTrustedDeviceDays($context->getSalesChannelId());
        if ($trustDevice && $days > 0 && $customer !== null) {
            $response->headers->setCookie($this->trustedDeviceService->createCookie($customer->getId(), $days));
        }

        return $response;
    }

    private function sendEmailCode(Request $request, CustomerEntity $customer, SalesChannelContext $context): bool
    {
        try {
            if ($this->emailCodeService->send($request->getSession(), $customer, $context)) {
                return true;
            }

            $this->addFlash(self::DANGER, $this->trans('tinect-2fa.login.email-failed'));
        } catch (RateLimitExceededException $exception) {
            $this->addFlash(self::DANGER, $this->trans('tinect-2fa.login.email-throttled', ['%seconds%' => $exception->getWaitTime()]));
        }

        return false;
    }

    private static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return $email;
        }

        return mb_substr($email, 0, 1) . str_repeat('*', max(3, $at - 1)) . substr($email, $at);
    }

    private function redirectTo(Request $request, string $defaultRoute): Response
    {
        if ($request->query->getString('redirectTo') === '') {
            return $this->redirectToRoute($defaultRoute);
        }

        return $this->createActionResponse($request);
    }
}
