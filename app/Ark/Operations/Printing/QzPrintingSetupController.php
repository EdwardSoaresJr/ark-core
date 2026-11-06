<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Set Up Printing: workstation setup downloads, connection check, printer choice and a test label.
 */
final class QzPrintingSetupController
{
    private const SIGNED_DOWNLOAD_MINUTES = 30;

    public function show(Request $request): View
    {
        $health = QzTraySigning::healthSnapshot();
        $settings = ShopSettings::current();
        $setupAvailable = $health['ready'] && $health['chains_to_trust_root'] && QzWorkstationSetup::available();

        return view('operations.printing.setup', [
            'signingReady' => $health['ready'],
            'setupAvailable' => $setupAvailable,
            'certificate' => $health['certificate'],
            'certificateExpiresSoon' => $health['certificate_expires_soon'],
            'trustRoot' => $setupAvailable ? QzWorkstationSetup::trustRoot() : null,
            'setupVersion' => QzWorkstationSetup::VERSION,
            'windowsDownloadUrl' => $setupAvailable ? route('operations.printing.setup.download', ['platform' => 'windows']) : null,
            'macosDownloadUrl' => $setupAvailable ? route('operations.printing.setup.download', ['platform' => 'macos']) : null,
            'macosCommandUrl' => $setupAvailable
                ? URL::temporarySignedRoute('operations.printing.setup.signed-download', now()->addMinutes(self::SIGNED_DOWNLOAD_MINUTES), ['platform' => 'macos'])
                : null,
            'testLabelUrl' => route('operations.printing.test-label'),
            'savePrintersUrl' => route('operations.printing.setup.printers'),
            'canManagePrinting' => $request->user()?->can(ArkCapability::SettingsManage->value) ?? false,
            'keyTagPrinter' => (string) ($settings->qz_printing_key_tag_printer ?? ''),
            'oilStickerPrinter' => (string) ($settings->qz_printing_oil_sticker_printer ?? ''),
            'printingSettingsUrl' => route('operations.settings.shop.edit', ['section' => 'printing']),
        ]);
    }

    public function download(Request $request, string $platform): Response
    {
        return $this->setupFile($request, $platform);
    }

    public function signedDownload(Request $request, string $platform): Response
    {
        abort_unless($platform === 'macos', 404);

        return $this->setupFile($request, $platform);
    }

    public function testLabel(LabelPdfRenderer $renderer): Response
    {
        $w = ShopPrintingSettings::keyTagLabelWidthMm();
        $h = ShopPrintingSettings::keyTagLabelHeightMm();
        $bytes = $renderer->renderBytes('operations.printing.test-label', [
            'shopName' => trim((string) (ShopSettings::current()->shop_name ?? '')) ?: 'ARK',
            'printedAt' => now()->format('Y-m-d H:i'),
            'labelWidthMm' => $w,
            'labelHeightMm' => $h,
        ], $w, $h);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="ARK-Printing-Test-Label.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function updatePrinters(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qz_printing_key_tag_printer' => ['nullable', 'string', 'max:255'],
            'qz_printing_oil_sticker_printer' => ['nullable', 'string', 'max:255'],
        ]);

        $changes = ['qz_printing_enabled' => true];
        foreach (['qz_printing_key_tag_printer', 'qz_printing_oil_sticker_printer'] as $field) {
            if ($request->exists($field)) {
                $changes[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
            }
        }
        ShopSettings::current()->update($changes);
        ShopSettings::forgetCurrent();

        return response()->json([
            'key_tag_printer' => ShopPrintingSettings::keyTagPrinter(),
            'oil_sticker_printer' => ShopPrintingSettings::oilStickerPrinter(),
        ]);
    }

    private function setupFile(Request $request, string $platform): Response
    {
        abort_unless(in_array($platform, QzWorkstationSetup::PLATFORMS, true), 404);

        $health = QzTraySigning::healthSnapshot();
        abort_unless($health['ready'] && $health['chains_to_trust_root'], 409, 'This shop has no working printing certificate yet.');

        $host = $request->getHost();
        try {
            $script = QzWorkstationSetup::render($platform, $host, route('operations.printing.setup'));
        } catch (RuntimeException $e) {
            Log::warning('qz.workstation_setup_unavailable', ['platform' => $platform, 'message' => $e->getMessage()]);
            abort(409, 'The printing setup is not available for this shop.');
        }

        return response($script, 200, [
            'Content-Type' => $platform === 'windows' ? 'application/octet-stream' : 'text/x-shellscript; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.QzWorkstationSetup::fileName($platform, $host).'"',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
