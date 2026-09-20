<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use RuntimeException;

final class InvoiceDocumentGuard
{
    public function ensureMutable(EstimateDocument $document): void
    {
        if (! $document->isInvoice()) {
            return;
        }

        if ($document->isIssuedInvoice()) {
            throw new RuntimeException('Issued final invoices are immutable. Use credit memo, adjustment, or refund workflows.');
        }
    }
}
