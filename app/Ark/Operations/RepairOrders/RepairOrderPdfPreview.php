<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocument;

/**
 * Where the RO workspace's inline PDF viewer (View Estimate / View Invoice) points.
 *
 * It names the existing authoritative PDF endpoints. It renders nothing itself, and its
 * preview URLs never mark an invoice as presented to the customer.
 *
 * @phpstan-type Preview array{
 *     document_id: ?int,
 *     title: string,
 *     view_url: string,
 *     download_url: string,
 *     new_tab_url: string,
 *     cache_bust: bool
 * }
 */
final readonly class RepairOrderPdfPreview
{
    /**
     * The current estimate PDF, the same one Estimate PDF opens and the customer link serves.
     *
     * @return Preview
     */
    public static function estimate(RepairOrder $repairOrder): array
    {
        $url = route('operations.repair-orders.estimate.pdf', $repairOrder);

        return [
            'document_id' => EstimateDocument::query()
                ->where('repair_order_id', $repairOrder->id)
                ->where('document_type', 'estimate')
                ->value('id'),
            'title' => 'Estimate · RO #'.$repairOrder->repair_order_id,
            'view_url' => $url,
            'download_url' => route('operations.repair-orders.estimate.pdf.download', $repairOrder),
            'new_tab_url' => $url,
            'cache_bust' => true,
        ];
    }

    /**
     * The issued invoice PDF as stored (snapshot authority); never re-rendered once issued.
     *
     * @return Preview
     */
    public static function invoice(RepairOrder $repairOrder, EstimateDocument $invoice): array
    {
        $url = route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $invoice, 'preview' => 1]);

        return [
            'document_id' => $invoice->id,
            'title' => 'Invoice · RO #'.$repairOrder->repair_order_id,
            'view_url' => $url,
            'download_url' => route('operations.repair-orders.estimate-documents.pdf.download', [$repairOrder, $invoice]),
            'new_tab_url' => $url,
            'cache_bust' => false,
        ];
    }
}
