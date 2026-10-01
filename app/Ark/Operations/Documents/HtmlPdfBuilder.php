<?php

namespace App\Ark\Operations\Documents;

use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

class HtmlPdfBuilder
{
    public function toPdfBytes(string $html): string
    {
        $this->assertRuntimeIsReady();

        $temporaryPath = tempnam(sys_get_temp_dir(), 'ark-sheet-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary PDF file.');
        }

        try {
            $this->browser($html)->savePdf($temporaryPath);

            $bytes = file_get_contents($temporaryPath);

            if ($bytes === false) {
                throw new RuntimeException('Generated PDF could not be read.');
            }

            return $bytes;
        } catch (Throwable $exception) {
            throw new RuntimeException('PDF generation failed: '.$exception->getMessage(), previous: $exception);
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function assertRuntimeIsReady(): void
    {
        $node = config('services.pdf.node_binary');
        $chrome = config('services.pdf.chrome_path');

        if (! is_string($node) || $node === '' || ! is_executable($node)) {
            throw new RuntimeException('PDF runtime is missing Node. Set PDF_NODE_BINARY or run install-pdf-runtime.');
        }

        if (! is_string($chrome) || $chrome === '' || ! is_executable($chrome)) {
            throw new RuntimeException('PDF runtime is missing Chromium. Set PDF_CHROME_PATH or run: npx puppeteer browsers install chrome-headless-shell');
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
}
