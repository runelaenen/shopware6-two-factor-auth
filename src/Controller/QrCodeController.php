<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QrCodeController
{
    /**
     * @Route("admin/rl-2fa/qr-code/secret.png", name="rl-2fa.qr-code.secret", methods={"GET"}, defaults={"auth_required"=false, "_routeScope"={"administration"}})
     */
    public function qrCode(Request $request): Response
    {
        $qrUrl = $request->get('qrUrl', '');

        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new SvgImageBackEnd()
        );

        $qrCode = (new Writer($renderer))
            ->writeString($qrUrl);

        return new Response(
            $qrCode,
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/svg+xml',
            ]
        );
    }
}
