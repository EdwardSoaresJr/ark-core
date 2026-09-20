<?php

namespace App\Ark\Operations\Documents;

final class DocumentPdfPath
{
    public static function for(EstimateDocument $document): string
    {
        $filename = $document->isInvoice() ? 'current-invoice.pdf' : 'current-estimate.pdf';

        return sprintf(
            'estimate-documents/ro-%d/%s',
            $document->repair_order_id,
            $filename,
        );
    }

    public static function matches(EstimateDocument $document): bool
    {
        return filled($document->pdf_path)
            && $document->pdf_path === self::for($document);
    }
}
