<?php

namespace App\Ark\Operations\Parts;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\Parser;

final class DealerQuoteTextExtractor
{
    public function __construct(
        private readonly DealerQuoteOcr $ocr = new DealerQuoteOcr,
    ) {}

    public function fromUpload(?UploadedFile $file, ?string $pastedText): string
    {
        $pasted = trim((string) $pastedText);

        if ($file !== null) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $mime = (string) $file->getMimeType();
            $path = $file->getRealPath() ?: $file->getPathname();

            if ($extension === 'pdf' || str_contains($mime, 'pdf')) {
                return $this->fromPdfPath($path);
            }

            if (
                str_starts_with($mime, 'image/')
                || in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff'], true)
            ) {
                return $this->ocr->fromImage($path);
            }

            if (str_starts_with($mime, 'text/') || in_array($extension, ['txt', 'csv'], true)) {
                $contents = trim((string) file_get_contents($path));

                if ($contents !== '') {
                    return $contents;
                }
            }

            throw new RuntimeException('Upload a PDF, quote photo, or paste the quote text.');
        }

        if ($pasted !== '') {
            return $pasted;
        }

        throw new RuntimeException('Upload a PDF, quote photo, or paste the quote text.');
    }

    public function fromPdfPath(string $path): string
    {
        if (! is_readable($path)) {
            throw new RuntimeException('Could not read the uploaded PDF.');
        }

        $text = '';

        try {
            $pdf = (new Parser)->parseFile($path);
            $text = trim((string) $pdf->getText());
        } catch (\Throwable) {
            $text = '';
        }

        if ($this->hasUsableText($text)) {
            return $text;
        }

        return $this->ocr->fromPdf($path);
    }

    private function hasUsableText(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        // Ignore PDFs that only contain whitespace / form-feed noise.
        $compact = preg_replace('/\s+/u', '', $text) ?? '';

        return strlen($compact) >= 24;
    }
}
