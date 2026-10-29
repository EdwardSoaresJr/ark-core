<?php

namespace Tests\Support;

use App\Ark\Operations\Documents\DocumentPdfPath;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use Illuminate\Support\Facades\Storage;

final class TestingFakePdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        $path = DocumentPdfPath::for($document);
        Storage::disk('local')->put($path, "%PDF-1.4\n%ARK-TEST-FAKE\n");
        $document->forceFill([
            'pdf_path' => $path,
            'generated_at' => $document->generated_at ?? now(),
            'needs_pdf_refresh' => false,
            'pdf_refreshed_at' => now(),
            'status' => $document->isInvoice() ? $document->status : 'generated',
        ])->save();

        return $path;
    }
}
