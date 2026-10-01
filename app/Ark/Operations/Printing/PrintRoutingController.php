<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PrintRoutingController
{
    public function __construct(
        private readonly RasterDpiResolver $rasterDpi,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', '');
        if ($type === '' || ! PrintRoutingService::isKnownDocumentType($type)) {
            return response()->json([
                'error' => 'invalid_type',
                'allowed' => PrintRoutingService::knownDocumentTypes(),
            ], 422);
        }

        $printer = PrintRoutingService::resolvePrinterName($type);

        $payload = [
            'document' => $type,
            'printer' => $printer,
            'location_id' => null,
        ];

        if (in_array($type, [
            PrintRoutingService::DOC_KEY_TAG,
            PrintRoutingService::DOC_KEY_TAG_TEST,
            PrintRoutingService::DOC_PARTS_LABEL,
        ], true)) {
            $dpi = $this->rasterDpi->resolve($request->userAgent());
            $page = ShopPrintingSettings::keyTagQzPage();
            $payload['dpi'] = $dpi;
            $payload['width_mm'] = $page['width_mm'];
            $payload['height_mm'] = $page['height_mm'];
            $payload['key_tag_orientation'] = ShopPrintingSettings::keyTagQzOrientation();
            if ($type !== PrintRoutingService::DOC_PARTS_LABEL) {
                $payload['key_tag_vin_display'] = ShopPrintingSettings::keyTagVinDisplayMode();
            }
        }

        if (in_array($type, [PrintRoutingService::DOC_OIL_CHANGE_STICKER, PrintRoutingService::DOC_OIL_CHANGE_STICKER_TEST], true)) {
            $dpi = $this->rasterDpi->resolve($request->userAgent());
            $page = ShopPrintingSettings::oilStickerQzPage();
            $payload['dpi'] = $dpi;
            $payload['width_mm'] = $page['width_mm'];
            $payload['height_mm'] = $page['height_mm'];
            $payload['key_tag_orientation'] = ShopPrintingSettings::oilStickerQzOrientation();
        }

        return response()->json($payload);
    }
}
