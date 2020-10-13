<?php


namespace RuneLaenen\TwoFactorAuth\Controller;


use RuneLaenen\TwoFactorAuth\Event\StorefrontTwoFactorAuthEvent;
use RuneLaenen\TwoFactorAuth\Event\StorefrontTwoFactorCancelEvent;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class StorefrontTwoFactorAuthController extends StorefrontController
{
    /**
     * @var TimebasedOneTimePasswordServiceInterface
     */
    private $totpService;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var AbstractLogoutRoute
     */
    private $logoutRoute;

    public function __construct(
        TimebasedOneTimePasswordServiceInterface $totpService,
        EventDispatcherInterface $dispatcher,
        AbstractLogoutRoute $logoutRoute
    ) {
        $this->totpService = $totpService;
        $this->dispatcher = $dispatcher;
        $this->logoutRoute = $logoutRoute;
    }

    /**
     * @Route("/rl-2fa/verification", name="frontend.rl2fa.verification", methods={"GET", "POST"})
     */
    public function verification(Request $request, SalesChannelContext $context)
    {
        if (!$context->getCustomer() || !$context->getCustomer()->getCustomFields() || empty($context->getCustomer()->getCustomFields()['rl_2fa_secret'])) {
            return $this->redirectToRoute('frontend.home.page');
        }

        if ($request->getMethod() === "POST") {
            $code = $request->get('otpCode');

            if ($this->totpService->verifyCode(
                $context->getCustomer()->getCustomFields()['rl_2fa_secret'],
                $code
            )) {
                $this->dispatcher->dispatch(new StorefrontTwoFactorAuthEvent($context));

                return $this->redirectToRoute('frontend.home.page');
            }

            $this->addFlash('danger', $this->trans('rl-2fa.account.error.incorrect-code'));
        }

        return $this->render('@RuneLaenenTwoFactorAuth/storefront/page/2fa/verification.html.twig');
    }

    /**
     * @Route("/rl-2fa/verification/cancel", name="frontend.rl2fa.verification.cancel", methods={"GET"})
     */
    public function cancelVerification(Request $request, SalesChannelContext $context)
    {
        $this->logoutRoute->logout($context);
        $this->dispatcher->dispatch(new StorefrontTwoFactorCancelEvent($context));

        return $this->redirectToRoute('frontend.account.login.page');
    }


}
