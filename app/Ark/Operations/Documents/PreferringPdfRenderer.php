<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chromium is preferred for fidelity. Native PHP (mPDF) is first-class when
 * Chromium/Node is unavailable — soft-fail is not an acceptable shared-host outcome.
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
            Log::notice('Chromium PDF unavailable; using native PHP PDF engine.', [
                'document_id' => $document->id,
                'reason' => $exception->getMessage(),
            ]);

            return $this->native->renderEstimate($document);
        } catch (Throwable $exception) {
            if (! $this->looksLikeMissingChromiumRuntime($exception)) {
                throw $exception;
            }

            Log::notice('Chromium PDF failed; using native PHP PDF engine.', [
                'document_id' => $document->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->native->renderEstimate($document);
        }
    }

    private function looksLikeMissingChromiumRuntime(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'chromium')
            || str_contains($message, 'chrome')
            || str_contains($message, 'node')
            || str_contains($message, 'puppeteer')
            || str_contains($message, 'browsershot');
    }
}
