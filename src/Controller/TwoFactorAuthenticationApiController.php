<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class TwoFactorAuthenticationApiController extends AbstractController
{
    /**
     * @var TimebasedOneTimePasswordServiceInterface
     */
    private $totpService;

    public function __construct(
        TimebasedOneTimePasswordServiceInterface $totpService
    ) {
        $this->totpService = $totpService;
    }

    /**
     * @Route("/api/v{version}/rl-2fa/generate-secret", name="api.action.rl-2fa.generate-secret", methods={"GET"})
     */
    public function generateSecret(Request $request, Context $context): JsonResponse
    {
        $secret = $this->totpService->createSecret();
        return new JsonResponse([
            'secret' => $secret,
            'qrUrl' => $this->totpService->getQrCodeUrl(
                'Test',
                'Rune',
                $secret
            )
        ]);
    }

    /**
     * @Route("/api/v{version}/rl-2fa/validate-secret", name="api.action.rl-2fa.validate-secret", methods={"POST"})
     */
    public function validateSecret(Request $request, Context $context): JsonResponse
    {
        if (empty($request->get('secret')) || empty($request->get('code'))) {
            return new JsonResponse([
                'status' => 'error',
                'error' => 'Secret or code empty'
            ], 400);
        }

        $verified = $this->totpService->verifyCode(
            (string) $request->get('secret'),
            (string) $request->get('code')
        );

        if ($verified) {
            return new JsonResponse([
                'status' => 'OK'
            ]);
        }

        return new JsonResponse([
            'status' => 'error',
            'error' => 'Secret and code not correct'
        ], 400);
    }
}
