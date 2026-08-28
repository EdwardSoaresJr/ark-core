<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

class HeadlessChromiumPdfRenderer implements PdfRenderer
{
    public function __construct(
        private readonly EstimateDocumentPdfSnapshot $pdfSnapshot,
    ) {}

    public function renderEstimate(EstimateDocument $document): string
    {
        $preservedInvoiceStatus = $document->isInvoice() ? $document->status : null;

        if (! $document->isInvoice()) {
            $document->forceFill([
                'status' => 'generating',
            ])->save();
        }

        $path = DocumentPdfPath::for($document);
        $absolutePath = $this->prepareEstimateOutputPath($path);
        $html = view('operations.documents.pdf.document', [
            'document' => $document,
            'snapshot' => $this->pdfSnapshot->resolve($document),
        ])->render();

        try {
            $this->assertRuntimeIsReady();
            $this->browser($html)->savePdf($absolutePath);
            @chmod($absolutePath, 0664);
        } catch (Throwable $exception) {
            if (! $document->isInvoice()) {
                $document->forceFill([
                    'status' => 'failed',
                ])->save();
            }

            $processOutput = method_exists($exception, 'getProcess')
                ? trim((string) $exception->getProcess()?->getErrorOutput())
                : '';

            Log::error('Estimate PDF generation failed.', [
                'document_id' => $document->id,
                'repair_order_id' => $document->repair_order_id,
                'pdf_path' => $path,
                'absolute_path' => $absolutePath,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'process_stderr' => $processOutput !== '' ? mb_substr($processOutput, 0, 2000) : null,
            ]);

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

    private function assertRuntimeIsReady(): void
    {
        $node = config('services.pdf.node_binary');
        $chrome = config('services.pdf.chrome_path');

        if (! is_string($node) || $node === '' || ! is_executable($node)) {
            throw new RuntimeException('Estimate PDF runtime is missing Node. Set PDF_NODE_BINARY or run install-pdf-runtime.');
        }

        if (! is_string($chrome) || $chrome === '' || ! is_executable($chrome)) {
            throw new RuntimeException('Estimate PDF runtime is missing Chromium. Set PDF_CHROME_PATH or run: npx puppeteer browsers install chrome-headless-shell');
        }
    }

    private function browser(string $html): Browsershot
    {
        $browser = Browsershot::html($html)
            ->format('Letter')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->waitUntilNetworkIdle();

        if ($nodeBinary = config('services.pdf.node_binary')) {
            $browser->setNodeBinary((string) $nodeBinary);
        }

        if ($npmBinary = config('services.pdf.npm_binary')) {
            $browser->setNpmBinary((string) $npmBinary);
        }

        if ($chromePath = config('services.pdf.chrome_path')) {
            $browser->setChromePath((string) $chromePath);
        }

        if ($includePath = config('services.pdf.include_path')) {
            $browser->setIncludePath((string) $includePath);
        }

        if (config('services.pdf.no_sandbox')) {
            $browser->noSandbox();
        }

        $environment = array_filter([
            'PUPPETEER_CACHE_DIR' => env('PUPPETEER_CACHE_DIR'),
        ]);

        if ($environment !== []) {
            $browser->setEnvironmentOptions($environment);
        }

        return $browser;
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
