<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Service\ConfigurationService;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class TwoFactorAuthenticationApiController extends AbstractController
{
    public function __construct(
        private TimebasedOneTimePasswordServiceInterface $totpService,
        private RouterInterface $router,
        private ConfigurationService $configurationService
    ) {
    }

    /**
     * @Route("/api/rl-2fa/generate-secret", name="api.action.rl-2fa.generate-secret", methods={"GET"})
     */
    public function generateSecret(Request $request, Context $context): JsonResponse
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
                [
                    'qrUrl' => $qrUrl,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);
    }

    /**
     * @Route("/api/rl-2fa/validate-secret", name="api.action.rl-2fa.validate-secret", methods={"POST"})
     */
    public function validateSecret(Request $request, Context $context): JsonResponse
    {
        if (empty($request->get('secret')) || empty($request->get('code'))) {
            return new JsonResponse([
                'status' => 'error',
                'error' => 'Secret or code empty',
            ], 400);
        }

        $verified = $this->totpService->verifyCode(
            (string) $request->get('secret'),
            (string) $request->get('code')
        );

        if ($verified) {
            return new JsonResponse([
                'status' => 'OK',
            ]);
        }

        return new JsonResponse([
            'status' => 'error',
            'error' => 'Secret and code not correct',
        ], 400);
    }
}
