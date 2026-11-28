<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Support\Facades\Log;

/**
 * Chromium is preferred for fidelity. Native PHP (mPDF) is first-class when
 * Chromium/Node is unavailable. Unrelated rendering errors are not swallowed.
 */
final class PreferringPdfRenderer implements PdfRenderer
{
    public function __construct(
        private readonly HeadlessChromiumPdfRenderer $chromium,
        private readonly NativePhpPdfRenderer $native,
    ) {}

    public function renderEstimate(EstimateDocument $document): string
    {
        try {
            return $this->chromium->renderEstimate($document);
        } catch (PdfRuntimeUnavailable $exception) {
            Log::notice('Chromium PDF runtime unavailable; using native PHP PDF engine.', [
                'document_id' => $document->id,
                'reason' => $exception->getMessage(),
            ]);

            return $this->native->renderEstimate($document);
        }
    }
}
