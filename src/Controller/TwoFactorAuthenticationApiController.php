<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Service\ConfigurationService;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[Route(path: '/api/_action/rl-2fa', defaults: ['_routeScope' => ['api']])]
class TwoFactorAuthenticationApiController extends AbstractController
{
    public function __construct(
        private readonly TimebasedOneTimePasswordServiceInterface $totpService,
        private readonly RouterInterface $router,
        private readonly ConfigurationService $configurationService
    ) {
    }

    #[Route(path: '/generate-secret', name: 'api.action.rl-2fa.generate-secret', methods: ['GET'])]
public function generateSecret(Request $request): JsonResponse
{
    $company = $this->configurationService->getAdministrationCompany(
        $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID)
    );

    $secret = $this->totpService->createSecret();
    $qrUrl = $this->totpService->getQrCodeUrl(
        $company,
        $request->get('holder', ''),
        $secret
    );

    // Get client's IP address
    $ipAddress = $request->getClientIp();

    // Store secret and IP address in the customer's custom fields
    $customerId = $request->get('customerId');
    $this->customerRepository->update([
        [
            'id' => $customerId,
            'customFields' => [
                'rl_2fa_secret' => $secret,
                'rl_2fa_ip' => $ipAddress,
            ],
        ],
    ], $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT));

    return new JsonResponse([
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
}
