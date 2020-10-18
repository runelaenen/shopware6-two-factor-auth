<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Subscriber;

use RuneLaenen\TwoFactorAuth\Controller\StorefrontTwoFactorAuthController;
use RuneLaenen\TwoFactorAuth\Event\StorefrontTwoFactorAuthEvent;
use RuneLaenen\TwoFactorAuth\Event\StorefrontTwoFactorCancelEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class CustomerLoginSubscriber implements EventSubscriberInterface
{
    const SESSION_NAME = 'RL_2FA_NEED_VERIFICATION';

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        SessionInterface $session,
        RouterInterface $router
    ) {
        $this->session = $session;
        $this->router = $router;
    }

    public static function getSubscribedEvents()
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
        if (!$this->session->has(self::SESSION_NAME)) {
            return;
        }

        $controller = $event->getController();
        if (is_array($controller)) {
            $controller = $controller[0];
        }
        if ($controller instanceof StorefrontTwoFactorAuthController) {
            return;
        }

        $url = $this->router->generate('frontend.rl2fa.verification');
        $response = new RedirectResponse($url);

        $response->send();
    }

    public function onCustomerLoginEvent(CustomerLoginEvent $event): void
    {
        if (!$event->getCustomer() || !$event->getCustomer()->getCustomFields()
            || empty($event->getCustomer()->getCustomFields()['rl_2fa_secret'])) {
            return;
        }

        $this->session->set(self::SESSION_NAME, true);
    }

    public function removeSession(): void
    {
        $this->session->remove(self::SESSION_NAME);
    }
}
