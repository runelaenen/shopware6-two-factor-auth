<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Service;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders the QR code inline, so the secret never ends up in a URL or a server access log.
 */
class QrCodeService
{
    public function createDataUri(string $content): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(400), new SvgImageBackEnd())))->writeString($content);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
