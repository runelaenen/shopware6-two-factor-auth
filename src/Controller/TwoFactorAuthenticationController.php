<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Service\ConfigurationService;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @RouteScope(scopes={"storefront"})
 */
class TwoFactorAuthenticationController extends StorefrontController
{
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var TimebasedOneTimePasswordServiceInterface
     */
    private $totpService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var LegacyPasswordVerifier
     */
    private $legacyPasswordVerifier;

    public function __construct(
        ConfigurationService $configurationService,
        TimebasedOneTimePasswordServiceInterface $totpService,
        RouterInterface $router,
        EntityRepositoryInterface $customerRepository,
        LegacyPasswordVerifier $legacyPasswordVerifier
    ) {
        $this->configurationService = $configurationService;
        $this->totpService = $totpService;
        $this->router = $router;
        $this->customerRepository = $customerRepository;
        $this->legacyPasswordVerifier = $legacyPasswordVerifier;
    }

    /**
     * @Route("/rl-2fa/profile/setup", name="rl-2fa.profile.setup", methods={"GET"}, defaults={"XmlHttpRequest"=true}))
     */
    public function profileSetup(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        if (!$this->configurationService->isStorefrontEnabled($salesChannelId) || !$salesChannelContext->getCustomer()) {
            return new Response();
        }

        $company = $this->configurationService->getStorefrontCompany(
            $salesChannelId
        );

        $secret = $this->totpService->createSecret();

        $qrUrl = $this->totpService->getQrCodeUrl(
            $company,
            $salesChannelContext->getCustomer()->getFirstName() . ' ' . $salesChannelContext->getCustomer()->getLastName(),
            $secret
        );

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/2fa/setup.html.twig', [
            'secret' => $secret,
            'qrUrl' => $this->router->generate(
                'rl-2fa.qr-code.secret',
                [
                    'qrUrl' => $qrUrl,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);
    }

    /**
     * @Route("/rl-2fa/profile/disable", name="rl-2fa.profile.disable", methods={"GET"}, defaults={"XmlHttpRequest"=true}))
     */
    public function profileDisable(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        if (!$this->configurationService->isStorefrontEnabled($salesChannelId)) {
            return new Response();
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/2fa/disable.html.twig');
    }

    /**
     * @Route("/rl-2fa/profile/disable", name="rl-2fa.profile.disable.post", methods={"POST"}, defaults={"XmlHttpRequest"=true}))
     */
    public function profileDisablePost(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$this->configurationService->isStorefrontEnabled($salesChannelContext->getSalesChannel()->getId())) {
            $this->addFlash('danger', $this->trans('rl-2fa.account.error.not-enabled'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        $customer = $salesChannelContext->getCustomer();
        $password = $request->get('otpPassword');
        if (!$customer) {
            $this->addFlash('danger', $this->trans('rl-2fa.account.error.no-customer'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        if ($customer->hasLegacyPassword()) {
            if (!$this->legacyPasswordVerifier->verify($password, $customer)) {
                $this->addFlash('danger', $this->trans('rl-2fa.account.error.incorrect-password'));

                return $this->redirectToRoute('frontend.account.profile.page');
            }
        } else {
            if (!password_verify($password, $customer->getPassword())) {
                $this->addFlash('danger', $this->trans('rl-2fa.account.error.incorrect-password'));

                return $this->redirectToRoute('frontend.account.profile.page');
            }
        }

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'customFields' => [
                    'rl_2fa_secret' => '',
                ],
            ],
        ], $salesChannelContext->getContext());

        $this->addFlash('info', $this->trans('rl-2fa.account.disabled-2fa'));

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Route("/rl-2fa/profile/validate", name="rl-2fa.profile.validate", methods={"POST"}, defaults={"XmlHttpRequest"=true}))
     */
    public function validateSecret(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$this->configurationService->isStorefrontEnabled($salesChannelContext->getSalesChannel()->getId())) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $this->trans('rl-2fa.account.error.not-enabled'),
            ], 400);
        }

        if (!$salesChannelContext->getCustomer()) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $this->trans('rl-2fa.account.error.no-customer'),
            ], 400);
        }

        if (empty($request->get('secret')) || empty($request->get('code'))) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $this->trans('rl-2fa.account.error.empty-input'),
            ], 400);
        }

        $verified = $this->totpService->verifyCode(
            (string) $request->get('secret'),
            (string) $request->get('code')
        );

        if ($verified) {
            $this->customerRepository->update([
                [
                    'id' => $salesChannelContext->getCustomer()->getId(),
                    'customFields' => [
                        'rl_2fa_secret' => (string) $request->get('secret'),
                    ],
                ],
            ], $salesChannelContext->getContext());

            return new JsonResponse([
                'status' => 'OK',
            ]);
        }

        return new JsonResponse([
            'status' => 'error',
            'error' => $this->trans('rl-2fa.account.error.incorrect-code'),
        ]);
    }
}
