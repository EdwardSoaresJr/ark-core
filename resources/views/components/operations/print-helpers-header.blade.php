@php
    $arkPrintLocId = null;
    $arkPrintKeyTagName = \App\Ark\Operations\Printing\ShopPrintingSettings::keyTagPrinter();
    $arkPrintOilStickerName = \App\Ark\Operations\Printing\ShopPrintingSettings::oilStickerPrinter();
    $arkPrintRoName = $arkPrintKeyTagName;
    $arkPrintLocationLabel = trim((string) (\App\Ark\Operations\Settings\ShopSettings::current()->shop_name ?? '')) ?: null;
    $arkPrintClientMetricsUrl = '';
    $arkQzSignUrl = route('operations.printing.qz.sign');
    $arkQzServerSigning = \App\Ark\Operations\Printing\QzTraySigning::isFullyConfigured();
    $arkQzServerCert = $arkQzServerSigning ? \App\Ark\Operations\Printing\QzTraySigning::certificateContents() : null;
    $arkQzServerSigning = $arkQzServerSigning
        && is_string($arkQzServerCert)
        && str_contains($arkQzServerCert, 'BEGIN CERTIFICATE')
        && str_contains($arkQzServerCert, 'END CERTIFICATE');
    if ($arkQzServerSigning) {
        $arkQzServerCert = trim($arkQzServerCert);
        if ($arkQzServerCert === ''
            || str_contains($arkQzServerCert, "\0")
            || stripos($arkQzServerCert, '</script>') !== false
        ) {
            $arkQzServerSigning = false;
            $arkQzServerCert = null;
        }
    }
    $arkQzJsSignAlgo = \App\Ark\Operations\Printing\QzTraySigning::javascriptSignatureAlgorithm();
    $arkKeyTagQzPage = \App\Ark\Operations\Printing\ShopPrintingSettings::keyTagQzPage();
    $arkKeyTagMediaType = \App\Ark\Operations\Printing\ShopPrintingSettings::keyTagMediaType();
    $arkKeyTagQzOrientation = \App\Ark\Operations\Printing\ShopPrintingSettings::keyTagQzOrientation();
    $arkKeyTagRasterDpi = app(\App\Ark\Operations\Printing\RasterDpiResolver::class)->resolve(request()->userAgent());
    $arkQlKeyTagScaleContent = \App\Ark\Operations\Printing\ShopPrintingSettings::qlKeyTagScaleContent();
    $arkOilStickerQzPage = \App\Ark\Operations\Printing\ShopPrintingSettings::oilStickerQzPage();
    $arkOilStickerQzOrientation = \App\Ark\Operations\Printing\ShopPrintingSettings::oilStickerQzOrientation();
    $arkQlOilStickerScaleContent = \App\Ark\Operations\Printing\ShopPrintingSettings::qlOilStickerScaleContent();
    $arkKeyTagQzDefaults = [
        'width_mm' => (float) config('printing.key_tag_qz_page.width_mm', 62),
        'height_mm' => (float) config('printing.key_tag_qz_page.height_mm', 38.1),
    ];
    $arkQzWizardUrl = route('operations.settings.shop.edit', ['section' => 'printing']);
    $arkPreflightKeyTagUrl = '';
    $arkPrintJsJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
    $arkPrintJsMsgNeedKeyTagPrinter = json_encode(__('Configure a key tag printer first'), $arkPrintJsJsonFlags);
    $arkPrintJsMsgLearnedCleared = json_encode(__('Learned printer profile cleared for this browser'), $arkPrintJsJsonFlags);
    $arkPrintJsMsgOpeningPreview = json_encode(__('Opening print preview. Print once from your browser if needed; the next key tag print from ARK will try the label printer again.'), $arkPrintJsJsonFlags);
    $arkUserIsAdminForPrintBanner = auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::SettingsManage->value) ?? false;
    $arkQzTrayScriptUrl = asset('vendor/qz/qz-tray.js');
    $arkQzLoadImmediately = false;
    $arkPrintingQlForceRaster = filter_var(config('printing.ql_force_raster', false), FILTER_VALIDATE_BOOLEAN);
    $arkQlKeyTagLockReferenceRaster = filter_var(config('printing.ql_key_tag_lock_reference_raster', true), FILTER_VALIDATE_BOOLEAN);
    $arkQlKeyTagLockReferencePx = config('printing.ql_key_tag_lock_reference_px', [203 => ['w' => 496, 'h' => 304], 300 => ['w' => 732, 'h' => 450]]);
    $arkQlLabelReferenceMm = config('printing.ql_label_reference_mm', ['width' => 62.0, 'height' => 38.1]);
    $arkPrintingPrinterResolveUrl = route('operations.printing.printer');
    $arkPdfJsVersion = '3.11.174';
    $arkPdfJsDistBase = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@'.$arkPdfJsVersion.'/legacy/build/';
@endphp
