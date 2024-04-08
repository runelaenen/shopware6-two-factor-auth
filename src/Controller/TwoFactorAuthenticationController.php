<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Service\ConfigurationService;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class TwoFactorAuthenticationController extends StorefrontController
{
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly TimebasedOneTimePasswordServiceInterface $totpService,
        private readonly RouterInterface $router,
        private readonly EntityRepository $customerRepository,
        private readonly LegacyPasswordVerifier $legacyPasswordVerifier
    ) {
    }

    #[Route(path: '/rl-2fa/profile/setup', name: 'widgets.rl-2fa.profile.setup', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function profileSetup(SalesChannelContext $salesChannelContext): Response
    {
        $customer = $salesChannelContext->getCustomer();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if ($customer === null || !$this->configurationService->isStorefrontEnabled($salesChannelId)) {
            return new Response();
        }

        $company = $this->configurationService->getStorefrontCompany(
            $salesChannelId
        );

        $secret = $this->totpService->createSecret();

        $qrUrl = $this->totpService->getQrCodeUrl(
            $company,
            $customer->getFirstName() . ' ' . $customer->getLastName(),
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

    #[Route(path: '/rl-2fa/profile/disable', name: 'widgets.rl-2fa.profile.disable', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function profileDisable(SalesChannelContext $salesChannelContext): Response
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if (!$this->configurationService->isStorefrontEnabled($salesChannelId)) {
            return new Response();
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/2fa/disable.html.twig');
    }

    #[Route(path: '/rl-2fa/profile/disable', name: 'widgets.rl-2fa.profile.disable.post', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function profileDisablePost(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$this->configurationService->isStorefrontEnabled($salesChannelContext->getSalesChannelId())) {
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

    #[Route(path: '/rl-2fa/profile/validate', name: 'widgets.rl-2fa.profile.validate', methods: ['POST'], defaults: ['XmlHttpRequest' => true])]
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
