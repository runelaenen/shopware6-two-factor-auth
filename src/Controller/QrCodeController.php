<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use Endroid\QrCode\QrCode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QrCodeController
{
    /**
     * @RouteScope(scopes={"administration"})
     * @Route("admin/rl-2fa/qr-code/secret.png", defaults={"auth_required"=false}, name="rl-2fa.qr-code.secret", methods={"GET"})
     */
    public function qrCode(Request $request, Context $context): Response
    {
        $qrUrl = $request->get('qrUrl', '');
        $qrCode = new QrCode($qrUrl);

        return new Response(
            $qrCode->writeString(),
            Response::HTTP_OK,
            ['Content-Type' => $qrCode->getContentType()]
        );
    }
}
