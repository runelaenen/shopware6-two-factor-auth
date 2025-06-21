<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Service\ConfigurationService;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AutoconfigureTag(name: 'controller.service_arguments')]
#[Route(path: '/api/_action/rl-2fa', defaults: ['_routeScope' => ['api']])]
class TwoFactorAuthenticationApiController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordService')]
        private readonly TimebasedOneTimePasswordServiceInterface $totpService,
        #[Autowire(service: 'Shopware\Storefront\Framework\Routing\Router')]
        private readonly RouterInterface $router,
        private readonly ConfigurationService $configurationService,
    ) {}

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

        return new JsonResponse([
            'secret' => $secret,
            'qrUrl' => $this->router->generate(
                'rl-2fa.qr-code.secret',
                ['qrUrl' => $qrUrl],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);
    }

    #[Route(path: '/validate-secret', name: 'api.action.rl-2fa.validate-secret', methods: ['POST'])]
    public function validateSecret(Request $request): JsonResponse
    {
        if (empty($request->get('secret')) || empty($request->get('code'))) {
            return new JsonResponse([
                'status' => 'error',
                'error' => 'Secret or code empty',
            ], 400);
        }

        $verified = $this->totpService->verifyCode((string) $request->get('secret'), (string) $request->get('code'));
        if ($verified) {
            return new JsonResponse(['status' => 'OK']);
        }

        return new JsonResponse([
            'status' => 'error',
            'error' => 'Secret and code not correct',
        ], Response::HTTP_BAD_REQUEST);
    }
}
