<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Service\ConfigurationService;
use RuneLaenen\TwoFactorAuth\Service\QrCodeService;
use RuneLaenen\TwoFactorAuth\Service\SecretStorage;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordService;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AutoconfigureTag(name: 'controller.service_arguments')]
#[Route(defaults: ['_routeScope' => ['storefront']])]
class TwoFactorAuthenticationController extends StorefrontController
{
    public function __construct(
        private readonly ConfigurationService $configurationService,
        #[Autowire(service: TimebasedOneTimePasswordService::class)]
        private readonly TimebasedOneTimePasswordServiceInterface $totpService,
        private readonly QrCodeService $qrCodeService,
        private readonly SecretStorage $secretStorage,
        private readonly AccountService $accountService,
    ) {
    }

    #[Route(
        path: '/rl-2fa/profile/setup',
        name: 'widgets.rl-2fa.profile.setup',
        defaults: ['XmlHttpRequest' => true],
        methods: ['GET'],
    )]
    public function profileSetup(SalesChannelContext $salesChannelContext): Response
    {
        $customer = $salesChannelContext->getCustomer();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if ($customer === null || !$this->configurationService->isStorefrontEnabled($salesChannelId)) {
            return new Response();
        }

        $company = $this->configurationService->getStorefrontCompany($salesChannelId);
        $secret = $this->totpService->createSecret();
        $qrUrl = $this->totpService->getQrCodeUrl(
            $company,
            $customer->getFirstName() . ' ' . $customer->getLastName(),
            $secret
        );

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/2fa/setup.html.twig', [
            'secret' => $secret,
            'qrUrl' => $this->qrCodeService->createDataUri($qrUrl),
        ]);
    }

    #[Route(
        path: '/rl-2fa/profile/disable',
        name: 'widgets.rl-2fa.profile.disable',
        defaults: ['XmlHttpRequest' => true],
        methods: ['GET'],
    )]
    public function profileDisable(SalesChannelContext $salesChannelContext): Response
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if (!$this->configurationService->isStorefrontEnabled($salesChannelId)) {
            return new Response();
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/2fa/disable.html.twig');
    }

    #[Route(
        path: '/rl-2fa/profile/disable',
        name: 'widgets.rl-2fa.profile.disable.post',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST'],
    )]
    public function profileDisablePost(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$this->configurationService->isStorefrontEnabled($salesChannelContext->getSalesChannelId())) {
            $this->addFlash('danger', $this->trans('rl-2fa.account.error.not-enabled'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        $customer = $salesChannelContext->getCustomer();
        $password = $request->request->getString('otpPassword');
        if (!$customer) {
            $this->addFlash('danger', $this->trans('rl-2fa.account.error.no-customer'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        try {
            $this->accountService->getCustomerByLogin($customer->getEmail(), $password, $salesChannelContext);
        } catch (CustomerException) {
            $this->addFlash('danger', $this->trans('rl-2fa.account.error.incorrect-password'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        $this->secretStorage->removeCustomerSecret($customer->getId());

        $this->addFlash('info', $this->trans('rl-2fa.account.disabled-2fa'));

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(
        path: '/rl-2fa/profile/validate',
        name: 'widgets.rl-2fa.profile.validate',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST'],
    )]
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

        $secret = $request->request->getString('secret');
        $code = $request->request->getString('code');
        if ($secret === '' || $code === '') {
            return new JsonResponse([
                'status' => 'error',
                'error' => $this->trans('rl-2fa.account.error.empty-input'),
            ], 400);
        }

        if ($this->totpService->verifyCode($secret, $code)) {
            $this->secretStorage->setCustomerSecret($salesChannelContext->getCustomer()->getId(), $secret);

            return new JsonResponse(['status' => 'OK']);
        }

        return new JsonResponse([
            'status' => 'error',
            'error' => $this->trans('rl-2fa.account.error.incorrect-code'),
        ]);
    }
}
