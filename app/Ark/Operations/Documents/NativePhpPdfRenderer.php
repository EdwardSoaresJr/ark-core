<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

/**
 * Table-oriented native PHP PDF path for hosts without Chromium/Node.
 * Uses the same customer snapshot preparation as Chromium so financial
 * accuracy and document semantics stay intact.
 */
class NativePhpPdfRenderer implements PdfRenderer
{
    public const VIEW = 'operations.documents.pdf.native-document';

    public function __construct(
        private readonly EstimateDocumentPdfSnapshot $pdfSnapshot,
        private readonly DocumentPdfPresenter $presenter,
    ) {}

    public function renderEstimate(EstimateDocument $document): string
    {
        if (! class_exists(Mpdf::class)) {
            throw new PdfRuntimeUnavailable('Native PHP PDF engine (mPDF) is not installed.');
        }

        $preservedInvoiceStatus = $document->isInvoice() ? $document->status : null;

        if (! $document->isInvoice()) {
            $document->forceFill([
                'status' => 'generating',
            ])->save();
        }

        $path = DocumentPdfPath::for($document);
        $absolutePath = $this->prepareEstimateOutputPath($path);
        $snapshot = $this->presenter->prepareForCustomer(
            $this->pdfSnapshot->resolve($document),
        );
        $html = view(self::VIEW, [
            'document' => $document,
            'snapshot' => $snapshot,
        ])->render();

        try {
            $temporaryPath = $absolutePath.'.rendering-'.bin2hex(random_bytes(6)).'.pdf';
            $tempDir = storage_path('app/mpdf-temp');
            if (! is_dir($tempDir)) {
                @mkdir($tempDir, 0755, true);
            }

            $mpdf = new Mpdf([
                'format' => 'Letter',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 12,
                'margin_bottom' => 14,
                'tempDir' => $tempDir,
                'default_font' => 'dejavusans',
            ]);
            $mpdf->SetTitle((string) data_get($snapshot, 'pdf_document_label', 'Document'));
            $mpdf->WriteHTML($html);
            $mpdf->Output($temporaryPath, Destination::FILE);
            @chmod($temporaryPath, 0664);
            if (! @rename($temporaryPath, $absolutePath)) {
                throw new \RuntimeException('Native PDF could not be moved into place.');
            }
        } catch (Throwable $exception) {
            if (isset($temporaryPath)) {
                @unlink($temporaryPath);
            }

            if (! $document->isInvoice()) {
                $document->forceFill([
                    'status' => 'failed',
                ])->save();
            }

            throw $exception;
        }

        $attributes = [
            'pdf_path' => $path,
            'generated_at' => $document->generated_at ?? now(),
            'needs_pdf_refresh' => false,
            'pdf_refreshed_at' => now(),
        ];

        if ($document->isInvoice()) {
            $attributes['status'] = $preservedInvoiceStatus;
        } else {
            $attributes['status'] = 'generated';
        }

        $document->forceFill($attributes)->save();

        return $path;
    }

    private function prepareEstimateOutputPath(string $path): string
    {
        $directory = dirname($path);
        Storage::disk('local')->makeDirectory($directory, 0775);
        $absoluteDirectory = Storage::disk('local')->path($directory);
        @chmod($absoluteDirectory, 02775);

        return Storage::disk('local')->path($path);
    }
}
