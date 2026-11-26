<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Controller;

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
use Tinect\TwoFactorAuth\Service\ConfigurationService;
use Tinect\TwoFactorAuth\Service\CustomerMethodResolver;
use Tinect\TwoFactorAuth\Service\QrCodeService;
use Tinect\TwoFactorAuth\Service\SecretStorage;
use Tinect\TwoFactorAuth\Service\TimebasedOneTimePasswordService;
use Tinect\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Tinect\TwoFactorAuth\Service\TotpVerifier;

#[AutoconfigureTag(name: 'controller.service_arguments')]
#[Route(defaults: ['_routeScope' => ['storefront']])]
class TwoFactorAuthenticationController extends StorefrontController
{
    /**
     * The secret of a running setup stays on the server, so nobody can bring their own.
     */
    private const SESSION_SETUP_SECRET = 'TINECT_2FA_SETUP_SECRET';

    public function __construct(
        private readonly ConfigurationService $configurationService,
        #[Autowire(service: TimebasedOneTimePasswordService::class)]
        private readonly TimebasedOneTimePasswordServiceInterface $totpService,
        private readonly QrCodeService $qrCodeService,
        private readonly SecretStorage $secretStorage,
        private readonly AccountService $accountService,
        private readonly CustomerMethodResolver $methodResolver,
        private readonly TotpVerifier $totpVerifier,
    ) {
    }

    #[Route(
        path: '/tinect-2fa/profile/setup',
        name: 'widgets.tinect-2fa.profile.setup',
        defaults: ['XmlHttpRequest' => true],
        methods: ['GET'],
    )]
    public function profileSetup(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $customer = $salesChannelContext->getCustomer();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if ($customer === null
            || !$this->configurationService->isStorefrontEnabled($salesChannelId)
            || $this->secretStorage->getCustomerSecret($customer->getId()) !== null
        ) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        $company = $this->configurationService->getStorefrontCompany($salesChannelId);
        $secret = $this->totpService->createSecret();
        $qrUrl = $this->totpService->getQrCodeUrl(
            $company,
            $customer->getFirstName() . ' ' . $customer->getLastName(),
            $secret
        );

        $request->getSession()->set(self::SESSION_SETUP_SECRET, $secret);

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/2fa/setup.html.twig', [
            'secret' => $secret,
            'qrUrl' => $this->qrCodeService->createDataUri($qrUrl),
            'passwordRequired' => $this->isPasswordRequired($customer->getId(), $salesChannelId),
        ]);
    }

    #[Route(
        path: '/tinect-2fa/profile/disable',
        name: 'widgets.tinect-2fa.profile.disable',
        defaults: ['XmlHttpRequest' => true],
        methods: ['GET'],
    )]
    public function profileDisable(SalesChannelContext $salesChannelContext): Response
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if (!$this->configurationService->isStorefrontEnabled($salesChannelId)) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/2fa/disable.html.twig');
    }

    #[Route(
        path: '/tinect-2fa/profile/disable',
        name: 'widgets.tinect-2fa.profile.disable.post',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST'],
    )]
    public function profileDisablePost(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$this->configurationService->isStorefrontEnabled($salesChannelContext->getSalesChannelId())) {
            $this->addFlash('danger', $this->trans('tinect-2fa.account.error.not-enabled'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        $customer = $salesChannelContext->getCustomer();
        $password = $request->request->getString('otpPassword');
        if (!$customer) {
            $this->addFlash('danger', $this->trans('tinect-2fa.account.error.no-customer'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        try {
            $this->accountService->getCustomerByLogin($customer->getEmail(), $password, $salesChannelContext);
        } catch (CustomerException) {
            $this->addFlash('danger', $this->trans('tinect-2fa.account.error.incorrect-password'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        $this->secretStorage->removeCustomerSecret($customer->getId());
        $this->secretStorage->removeCustomerEmail($customer->getId());

        $this->addFlash('info', $this->trans('tinect-2fa.account.disabled-2fa'));

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(
        path: '/tinect-2fa/profile/email/enable',
        name: 'widgets.tinect-2fa.profile.email.enable',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST'],
    )]
    public function profileEnableEmail(SalesChannelContext $salesChannelContext): Response
    {
        if (!$this->configurationService->isStorefrontEnabled($salesChannelContext->getSalesChannelId())) {
            $this->addFlash('danger', $this->trans('tinect-2fa.account.error.not-enabled'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            $this->addFlash('danger', $this->trans('tinect-2fa.account.error.no-customer'));

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        // The app is the stronger factor; switching away from it goes through the password-protected disable.
        if ($this->secretStorage->getCustomerSecret($customer->getId()) === null) {
            $this->secretStorage->enableCustomerEmail($customer->getId());
            $this->addFlash('success', $this->trans('tinect-2fa.account.email.enabled'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(
        path: '/tinect-2fa/profile/validate',
        name: 'widgets.tinect-2fa.profile.validate',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST'],
    )]
    public function validateSecret(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        if (!$this->configurationService->isStorefrontEnabled($salesChannelId)) {
            return $this->validationError('tinect-2fa.account.error.not-enabled', Response::HTTP_BAD_REQUEST);
        }

        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            return $this->validationError('tinect-2fa.account.error.no-customer', Response::HTTP_BAD_REQUEST);
        }

        // Replacing the app would hand the account to whoever holds the session, so it goes through the
        // password-protected disable first.
        if ($this->secretStorage->getCustomerSecret($customer->getId()) !== null) {
            return $this->validationError('tinect-2fa.account.error.already-enabled', Response::HTTP_BAD_REQUEST);
        }

        $session = $request->getSession();
        $secret = $session->get(self::SESSION_SETUP_SECRET);
        $code = $request->request->getString('code');
        if (!\is_string($secret) || $secret === '' || $code === '') {
            return $this->validationError('tinect-2fa.account.error.empty-input', Response::HTTP_BAD_REQUEST);
        }

        if ($this->isPasswordRequired($customer->getId(), $salesChannelId)) {
            try {
                $this->accountService->getCustomerByLogin(
                    $customer->getEmail(),
                    $request->request->getString('password'),
                    $salesChannelContext
                );
            } catch (CustomerException) {
                return $this->validationError('tinect-2fa.account.error.incorrect-password-setup');
            }
        }

        $timestep = $this->totpVerifier->getTimestep($secret, $code);
        if ($timestep === null) {
            return $this->validationError('tinect-2fa.account.error.incorrect-code');
        }

        $this->secretStorage->setCustomerSecret($customer->getId(), $secret, $timestep);
        $this->secretStorage->removeCustomerEmail($customer->getId());
        $session->remove(self::SESSION_SETUP_SECRET);

        return new JsonResponse(['status' => 'OK']);
    }

    /**
     * Switching from e-mail codes to the app replaces a factor, so it needs the password like disabling does.
     */
    private function isPasswordRequired(string $customerId, string $salesChannelId): bool
    {
        return $this->methodResolver->resolve($customerId, $salesChannelId) !== null;
    }

    private function validationError(string $snippet, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['status' => 'error', 'error' => $this->trans($snippet)], $status);
    }
}
