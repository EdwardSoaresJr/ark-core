<?php

namespace App\Ark\Operations\Documents;

/**
 * Hash of the HTML a document's PDF is rendered from, without the render time, so an unchanged
 * estimate can be served from its stored file instead of launching Chromium again.
 * Covers the snapshot, live shop presentation, and the PDF templates themselves.
 */
final class EstimatePdfContentFingerprint
{
    public function __construct(
        private readonly EstimateDocumentPdfSnapshot $pdfSnapshot,
    ) {}

    public function for(EstimateDocument $document): string
    {
        $snapshot = $this->pdfSnapshot->resolve($document);
        $snapshot['generated_at'] = null;
        $snapshot['generated_at_display'] = null;

        $html = view(HeadlessChromiumPdfRenderer::VIEW, [
            'document' => $document,
            'snapshot' => $snapshot,
        ])->render();

        return hash('sha256', $html);
    }
}
