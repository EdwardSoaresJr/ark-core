<?php

namespace App\Ark\Operations\Portal;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Restrained QR for customer-facing report footers (share URL only).
 */
final class CustomerReportQrCode
{
    public static function svgDataUri(string $url, int $size = 88): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd(),
        );

        $svg = (new Writer($renderer))->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
