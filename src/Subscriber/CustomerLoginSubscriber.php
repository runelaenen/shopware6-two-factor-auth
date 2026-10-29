<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Subscriber;

use RuneLaenen\TwoFactorAuth\Event\StorefrontTwoFactorAuthEvent;
use RuneLaenen\TwoFactorAuth\Event\StorefrontTwoFactorCancelEvent;
use RuneLaenen\TwoFactorAuth\Service\SecretStorage;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
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

#[AutoconfigureTag(name: 'kernel.event_subscriber')]
readonly class CustomerLoginSubscriber implements EventSubscriberInterface
{
    public const SESSION_NAME = 'RL_2FA_NEED_VERIFICATION';

    public const SESSION_TARGET_URL = 'RL_2FA_TARGET_URL';

    private const VERIFICATION_ROUTES = ['frontend.rl2fa.verification', 'frontend.rl2fa.verification.cancel'];

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire(service: 'router')]
        private RouterInterface $router,
        private SecretStorage $secretStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
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
        if ($request->isXmlHttpRequest()) {
            $event->setController(static fn (): Response => new Response('', Response::HTTP_FORBIDDEN));

            return;
        }

        if ($request->isMethod(Request::METHOD_GET)) {
            $targetUrl = $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI);
            if (\is_string($targetUrl) && self::isLocalPath($targetUrl)) {
                $session->set(self::SESSION_TARGET_URL, $targetUrl);
            }
        }

        $url = $this->router->generate('frontend.rl2fa.verification');
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

        if ($this->secretStorage->getCustomerSecret($event->getCustomer()->getId()) === null) {
            return;
        }

        $session = $request->getSession();
        $session->set(self::SESSION_NAME, true);
        $session->remove(self::SESSION_TARGET_URL);
    }

    public function removeSession(): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return;
        }

        $request->getSession()->remove(self::SESSION_NAME);
        $request->getSession()->remove(self::SESSION_TARGET_URL);
    }

    public static function isLocalPath(string $url): bool
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\');
    }
}
