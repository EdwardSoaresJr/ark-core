<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Throwable;

class HtmlPdfBuilder
{
    public function toPdfBytes(string $html): string
    {
        try {
            return $this->chromiumToPdfBytes($html);
        } catch (PdfRuntimeUnavailable $exception) {
            Log::notice('Chromium HTML PDF runtime unavailable; using native PHP PDF engine.', [
                'reason' => $exception->getMessage(),
            ]);

            return $this->nativeToPdfBytes($html);
        }
    }

    private function chromiumToPdfBytes(string $html): string
    {
        $this->assertChromiumRuntimeIsReady();

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
        } catch (PdfRuntimeUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->isProcessSpawnFailure($exception)) {
                throw new PdfRuntimeUnavailable($exception->getMessage(), previous: $exception);
            }

            throw new RuntimeException('PDF generation failed: '.$exception->getMessage(), previous: $exception);
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function nativeToPdfBytes(string $html): string
    {
        if (! class_exists(Mpdf::class)) {
            throw new PdfRuntimeUnavailable('Native PHP PDF engine (mPDF) is not installed.');
        }

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
        ]);
        // Statement/inspection templates often use flex; approximate with auto layout.
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function assertChromiumRuntimeIsReady(): void
    {
        if (! PdfRuntimePaths::processSpawningAvailable()) {
            throw new PdfRuntimeUnavailable(
                'PDF runtime cannot spawn processes (exec/proc_open disabled). Native PHP PDF engine will be used.'
            );
        }

        $node = config('services.pdf.node_binary');
        $chrome = config('services.pdf.chrome_path');

        if (! is_string($node) || $node === '' || ! is_executable($node)) {
            throw new PdfRuntimeUnavailable('PDF runtime is missing Node. Set PDF_NODE_BINARY or run install-pdf-runtime.');
        }

        if (! is_string($chrome) || $chrome === '' || ! is_executable($chrome)) {
            throw new PdfRuntimeUnavailable('PDF runtime is missing Chromium. Set PDF_CHROME_PATH or run: npx puppeteer browsers install chrome-headless-shell');
        }
    }

    private function isProcessSpawnFailure(Throwable $exception): bool
    {
        if ($exception instanceof ProcessFailedException || $exception instanceof ProcessLogicException) {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof ProcessFailedException || $previous instanceof ProcessLogicException) {
            return true;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'proc_open')
            || str_contains($message, 'exec()')
            || str_contains($message, 'Unable to launch the browser')
            || str_contains($message, 'browser.cjs');
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
