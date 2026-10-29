<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Event\StorefrontTwoFactorAuthEvent;
use RuneLaenen\TwoFactorAuth\Event\StorefrontTwoFactorCancelEvent;
use RuneLaenen\TwoFactorAuth\Service\SecretStorage;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use RuneLaenen\TwoFactorAuth\Subscriber\CustomerLoginSubscriber;
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

#[AutoconfigureTag(name: 'controller.service_arguments')]
#[Route(defaults: ['_routeScope' => ['storefront']])]
class StorefrontTwoFactorAuthController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: 'RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordService')]
        private readonly TimebasedOneTimePasswordServiceInterface $totpService,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $dispatcher,
        #[Autowire(service: 'Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute')]
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly RateLimiter $rateLimiter,
        private readonly SecretStorage $secretStorage,
    ) {
    }

    #[Route(path: '/rl-2fa/verification', name: 'frontend.rl2fa.verification', methods: ['GET', 'POST'])]
    public function verification(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        $twoFactorSecret = $customer === null ? null : $this->secretStorage->getCustomerSecret($customer->getId());

        if ($customer === null || $twoFactorSecret === null) {
            return $this->redirectTo($request, 'frontend.account.login.page');
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            $rateLimitKey = 'rl-2fa-' . $customer->getId();

            try {
                $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $rateLimitKey);

                if ($this->totpService->verifyCode($twoFactorSecret, $request->request->getString('otpCode'))) {
                    $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $rateLimitKey);

                    $targetUrl = $request->getSession()->get(CustomerLoginSubscriber::SESSION_TARGET_URL);
                    $this->dispatcher->dispatch(new StorefrontTwoFactorAuthEvent($context));

                    if (\is_string($targetUrl) && CustomerLoginSubscriber::isLocalPath($targetUrl)) {
                        return new RedirectResponse($targetUrl);
                    }

                    return $this->redirectTo($request, 'frontend.account.home.page');
                }

                $this->addFlash(self::DANGER, $this->trans('rl-2fa.account.error.incorrect-code'));
            } catch (RateLimitExceededException $exception) {
                $this->addFlash(self::DANGER, $this->trans('account.loginThrottled', ['%seconds%' => $exception->getWaitTime()]));
            }
        }

        return $this->renderStorefront('@RuneLaenenTwoFactorAuth/storefront/page/2fa/verification.html.twig', [
            'redirect' => $request->query->all(),
        ]);
    }

    #[Route(path: '/rl-2fa/verification/cancel', name: 'frontend.rl2fa.verification.cancel', methods: ['GET'])]
    public function cancelVerification(Request $request, SalesChannelContext $context, RequestDataBag $dataBag): Response
    {
        if ($context->getCustomer() !== null) {
            $this->logoutRoute->logout($context, $dataBag);
        }
        $this->dispatcher->dispatch(new StorefrontTwoFactorCancelEvent($context));

        return $this->redirectTo($request, 'frontend.account.login.page');
    }

    private function redirectTo(Request $request, string $defaultRoute): Response
    {
        if ($request->query->getString('redirectTo') === '') {
            return $this->redirectToRoute($defaultRoute);
        }

        return $this->createActionResponse($request);
    }
}
