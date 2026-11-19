<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Tinect\TwoFactorAuth\Event\StorefrontTwoFactorAuthEvent;
use Tinect\TwoFactorAuth\Event\StorefrontTwoFactorCancelEvent;
use Tinect\TwoFactorAuth\Service\CustomerMethodResolver;
use Tinect\TwoFactorAuth\Service\EmailCodeService;
use Tinect\TwoFactorAuth\Service\TrustedDeviceService;

#[AutoconfigureTag(name: 'kernel.event_subscriber')]
readonly class CustomerLoginSubscriber implements EventSubscriberInterface
{
    public const SESSION_NAME = 'TINECT_2FA_NEED_VERIFICATION';

    public const SESSION_TARGET_URL = 'TINECT_2FA_TARGET_URL';

    private const VERIFICATION_ROUTES = [
        'frontend.tinect2fa.verification',
        'frontend.tinect2fa.verification.resend',
        'frontend.tinect2fa.verification.cancel',
    ];

    private const REGISTERED_ATTRIBUTE = 'tinect2faRegistered';

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire(service: 'router')]
        private RouterInterface $router,
        private CustomerMethodResolver $methodResolver,
        private TrustedDeviceService $trustedDeviceService,
        private EmailCodeService $emailCodeService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerRegisterEvent::class => 'onCustomerRegisterEvent',
            CustomerLoginEvent::class => 'onCustomerLoginEvent',
            KernelEvents::CONTROLLER => 'onController',
            StorefrontTwoFactorAuthEvent::class => 'removeSession',
            StorefrontTwoFactorCancelEvent::class => 'removeSession',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()
            || !$request->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)
            || $request->attributes->get('_esi') === true
            || \in_array($request->attributes->get('_route'), self::VERIFICATION_ROUTES, true)
            || !$request->hasSession()
        ) {
            return;
        }

        $session = $request->getSession();
        if (!$session->has(self::SESSION_NAME)) {
            return;
        }

        // The customer is already logged in at this point, so every other route has to be blocked, AJAX ones included.
        // Subresources served by controllers (e.g. tracking scripts loaded by the verification page) must neither be
        // redirected nor be remembered as target URL, otherwise the customer lands on them after the verification.
        if ($request->isXmlHttpRequest() || !self::isNavigationRequest($request)) {
            $event->setController(static fn (): Response => new Response('forbidden', Response::HTTP_FORBIDDEN));

            return;
        }

        if ($request->isMethod(Request::METHOD_GET)) {
            $targetUrl = $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI);
            if (\is_string($targetUrl) && self::isLocalPath($targetUrl)) {
                $session->set(self::SESSION_TARGET_URL, $targetUrl);
            }
        }

        $url = $this->router->generate('frontend.tinect2fa.verification');
        $event->setController(static fn (): Response => new RedirectResponse($url));
    }

    public function onCustomerLoginEvent(CustomerLoginEvent $event): void
    {
        // An admin using "Log in as customer" can't know the customer's code.
        if ($event->getSalesChannelContext()->getImitatingUserId() !== null) {
            return;
        }

        // Store API logins have no storefront session; TwoFactorLoginRoute asks them for the code up front.
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession(true)) {
            return;
        }

        // Registration logs the new customer in right away. They created the account just now, nothing to protect yet.
        if ($request->attributes->getBoolean(self::REGISTERED_ATTRIBUTE)) {
            return;
        }

        $customer = $event->getCustomer();
        if ($customer->getGuest()) {
            return;
        }

        $method = $this->methodResolver->resolve($customer->getId(), $event->getSalesChannelContext()->getSalesChannelId());
        if ($method === null || $this->trustedDeviceService->isTrusted($request, $customer->getId())) {
            return;
        }

        $session = $request->getSession();
        $session->set(self::SESSION_NAME, true);
        $session->remove(self::SESSION_TARGET_URL);
        $this->emailCodeService->clear($session);
    }

    public function onCustomerRegisterEvent(): void
    {
        $this->requestStack->getMainRequest()?->attributes->set(self::REGISTERED_ATTRIBUTE, true);
    }

    public function removeSession(): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return;
        }

        $request->getSession()->remove(self::SESSION_NAME);
        $request->getSession()->remove(self::SESSION_TARGET_URL);
        $this->emailCodeService->clear($request->getSession());
    }

    private static function isNavigationRequest(Request $request): bool
    {
        $fetchDestination = $request->headers->get('Sec-Fetch-Dest');
        if ($fetchDestination !== null && $fetchDestination !== '') {
            return $fetchDestination === 'document';
        }

        // Browsers without Fetch Metadata: page navigations ask for HTML, scripts and the like don't.
        return str_contains((string) $request->headers->get('Accept'), 'text/html');
    }

    public static function isLocalPath(string $url): bool
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\');
    }
}
