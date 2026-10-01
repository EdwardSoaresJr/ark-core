<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

final class LabelPdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function renderBytes(string $view, array $data, float $widthMm, float $heightMm): string
    {
        $html = view($view, $data)->render();

        try {
            $this->assertRuntimeIsReady();

            return $this->browser($html, $widthMm, $heightMm)->pdf();
        } catch (Throwable $exception) {
            Log::error('Label PDF generation failed.', [
                'view' => $view,
                'width_mm' => $widthMm,
                'height_mm' => $heightMm,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function assertRuntimeIsReady(): void
    {
        $node = config('services.pdf.node_binary');
        $chrome = config('services.pdf.chrome_path');

        if (! is_string($node) || $node === '' || ! is_executable($node)) {
            throw new RuntimeException('Label PDF runtime is missing Node. Set PDF_NODE_BINARY or run install-pdf-runtime.');
        }

        if (! is_string($chrome) || $chrome === '' || ! is_executable($chrome)) {
            throw new RuntimeException('Label PDF runtime is missing Chromium. Set PDF_CHROME_PATH or run: npx puppeteer browsers install chrome-headless-shell');
        }
    }

    private function browser(string $html, float $widthMm, float $heightMm): Browsershot
    {
        $browser = Browsershot::html($html)
            ->paperSize($widthMm, $heightMm, 'mm')
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
}
