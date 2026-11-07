<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Support\Facades\Storage;

/**
 * Keeps the stored PDF of an invoice version when a newer version replaces it. The current invoice
 * PDF is rewritten in place, so a version's PDF survives only as this copy. A version whose PDF was
 * never rendered, or was rendered before its current content, keeps none: it is never re-created.
 * A payment recorded since rendering does not disqualify the file - it is still what the customer saw.
 */
final class InvoiceRevisionPdf
{
    public static function pathFor(EstimateDocument $invoice, int $revision): string
    {
        return sprintf(
            'estimate-documents/ro-%d/invoice-revisions/invoice-%d-revision-%d.pdf',
            $invoice->repair_order_id,
            $invoice->id,
            $revision,
        );
    }

    public static function preserveCurrent(EstimateDocument $invoice, int $revision): ?string
    {
        if (! DocumentPdfPath::matches($invoice)
            || ! self::renderedAfterLastContentChange($invoice)
            || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            return null;
        }

        $path = self::pathFor($invoice, $revision);

        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->copy($invoice->pdf_path, $path);
        }

        return $path;
    }

    private static function renderedAfterLastContentChange(EstimateDocument $invoice): bool
    {
        if ($invoice->refreshed_at === null) {
            return true;
        }

        return $invoice->pdf_refreshed_at !== null && $invoice->pdf_refreshed_at->gte($invoice->refreshed_at);
    }
}
