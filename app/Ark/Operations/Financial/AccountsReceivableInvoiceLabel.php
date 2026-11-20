<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Presentation-only AR invoice label. Uses the RO number advisors already know.
 * Does not change estimate_documents.document_number or financial document IDs.
 */
final class AccountsReceivableInvoiceLabel
{
    public static function forRepairOrderNumber(int|string|null $repairOrderNumber): string
    {
        $number = trim((string) ($repairOrderNumber ?? ''));

        if ($number === '') {
            return 'Invoice';
        }

        return 'RO #'.$number.' · Invoice';
    }

    public static function forRepairOrder(?RepairOrder $repairOrder): string
    {
        return self::forRepairOrderNumber($repairOrder?->repair_order_id);
    }

    public static function forDocument(?EstimateDocument $document): string
    {
        if ($document === null) {
            return 'Invoice';
        }

        $document->loadMissing('repairOrder');

        return self::forRepairOrder($document->repairOrder);
    }
}
