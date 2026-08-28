<?php

namespace App\Ark\Operations\Parts;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OCR fallback for scanned dealer-quote PDFs and quote photos.
 * Requires pdftoppm (poppler) for PDFs and tesseract for text recognition.
 */
final class DealerQuoteOcr
{
    private const MAX_PAGES = 4;

    public function available(): bool
    {
        return $this->binary('tesseract') !== null;
    }

    public function fromPdf(string $pdfPath): string
    {
        $pdftoppm = $this->binary('pdftoppm');
        $tesseract = $this->binary('tesseract');

        if ($pdftoppm === null || $tesseract === null) {
            throw new RuntimeException(
                'This PDF is a scan (no selectable text). OCR is not installed on this server yet — paste the quote text, or wait for the OCR deploy.'
            );
        }

        $workDir = storage_path('app/private/dealer-quotes/ocr/'.Str::uuid()->toString());
        if (! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Could not prepare OCR workspace.');
        }

        try {
            $prefix = $workDir.'/page';
            $render = Process::timeout(90)->run([
                $pdftoppm,
                '-png',
                '-r',
                '300',
                '-f',
                '1',
                '-l',
                (string) self::MAX_PAGES,
                $pdfPath,
                $prefix,
            ]);

            if (! $render->successful()) {
                throw new RuntimeException('Could not render PDF pages for OCR.');
            }

            $pages = glob($prefix.'-*.png') ?: [];
            sort($pages, SORT_NATURAL);

            if ($pages === []) {
                throw new RuntimeException('Could not render PDF pages for OCR.');
            }

            $chunks = [];

            foreach ($pages as $page) {
                $chunks[] = $this->ocrImage($page, $tesseract);
            }

            $text = trim(implode("\n\n", array_filter($chunks)));
            $text = $this->normalizeOcrText($text);

            if ($text === '') {
                throw new RuntimeException('OCR could not read any text from this PDF. Paste the quote text instead.');
            }

            return $text;
        } finally {
            $this->cleanupDirectory($workDir);
        }
    }

    public function fromImage(string $imagePath): string
    {
        $tesseract = $this->binary('tesseract');

        if ($tesseract === null) {
            throw new RuntimeException(
                'Quote photo OCR is not installed on this server yet — paste the quote text, or wait for the OCR deploy.'
            );
        }

        $text = $this->normalizeOcrText($this->ocrImage($imagePath, $tesseract));

        if ($text === '') {
            throw new RuntimeException('OCR could not read any text from this image. Paste the quote text instead.');
        }

        return $text;
    }

    private function normalizeOcrText(string $text): string
    {
        $text = str_replace(['—', '–', '−', '‐', '‑'], '-', trim($text));

        return preg_replace('/-{2,}/', '-', $text) ?? $text;
    }

    private function ocrImage(string $imagePath, string $tesseract): string
    {
        $result = Process::timeout(90)->run([
            $tesseract,
            $imagePath,
            'stdout',
            '-l',
            'eng',
            '--psm',
            '6',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('OCR failed while reading the quote. Paste the quote text instead.');
        }

        return trim($result->output());
    }

    private function binary(string $name): ?string
    {
        $paths = [
            '/opt/homebrew/bin/'.$name,
            '/usr/local/bin/'.$name,
            '/usr/bin/'.$name,
        ];

        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $which = Process::run(['which', $name]);

        if ($which->successful()) {
            $resolved = trim($which->output());

            return $resolved !== '' ? $resolved : null;
        }

        return null;
    }

    private function cleanupDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }
}
