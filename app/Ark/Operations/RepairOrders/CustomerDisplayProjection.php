<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\CustomerFacingEstimateStatus;
use App\Ark\Operations\Evidence\EvidenceAttachable;
use App\Ark\Operations\Evidence\EvidenceProjection;
use App\Ark\Operations\Financial\CustomerEstimateTotalsPresentation;
use App\Ark\Operations\Portal\PortalEstimateSnapshot;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Collection;

/**
 * Disposable kiosk projection for the front-counter customer display.
 *
 * Same customer-facing estimate truth as the portal, without portal chrome,
 * authorization, or deposit. Rebuild from the RO anytime.
 */
final class CustomerDisplayProjection
{
    public function __construct(
        private readonly PortalEstimateSnapshot $snapshot,
        private readonly EvidenceProjection $evidence,
        private readonly CustomerFacingEstimateStatus $estimateStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $snapshot = $this->snapshot->forRepairOrder($repairOrder);
        $photos = $this->evidence->forRepairOrder($repairOrder, customerFacing: true);
        $photosByConcern = $photos
            ->filter(fn (array $row): bool => ($row['attachable_kind'] ?? null) === EvidenceAttachable::KIND_CONCERN && ($row['is_image'] ?? false))
            ->groupBy(fn (array $row): int => (int) ($row['attachable_id'] ?? 0));

        $concerns = RecommendationIntent::sortedSnapshotConcerns($snapshot['concerns'] ?? [])
            ->map(function (array $concern) use ($photosByConcern): array {
                $id = (int) ($concern['id'] ?? 0);
                $intent = RecommendationIntent::fromStored((string) ($concern['recommendation_intent'] ?? ''));

                return [
                    'id' => $id,
                    'summary' => (string) ($concern['summary'] ?? ''),
                    'intent_label' => $intent->customerLabel(),
                    'subtotal' => (string) ($concern['subtotal'] ?? ''),
                    'disposition_label' => (string) ($concern['disposition_label'] ?? ''),
                    'lines' => $this->linesForConcern($concern),
                    'photos' => $this->photoRows($photosByConcern->get($id, collect())),
                ];
            })
            ->all();

        $totals = CustomerEstimateTotalsPresentation::fromSnapshotTotals(
            $snapshot['totals'] ?? [],
            data_get($snapshot, 'customer.customer_type'),
        );

        return [
            'shop_name' => ShopSettings::current()->displayName(),
            'vehicle_name' => (string) ($snapshot['vehicle']['display_name'] ?? $repairOrder->vehicle?->display_name ?? 'Vehicle'),
            'customer_first_name' => trim((string) ($repairOrder->customer?->first_name ?? '')),
            'repair_order_number' => $repairOrder->repairOrderId(),
            'status_label' => $this->estimateStatus->labelForSnapshot($snapshot),
            'concerns' => $concerns,
            'general_photos' => $this->photoRows(
                $photos->filter(fn (array $row): bool => ($row['attachable_kind'] ?? null) !== EvidenceAttachable::KIND_CONCERN && ($row['is_image'] ?? false)),
            ),
            'totals' => $totals,
            'total_label' => (string) data_get($snapshot, 'document_footer.total_label', 'Total'),
        ];
    }

    /**
     * @param  array<string, mixed>  $concern
     * @return list<array{description: string, quantity: string, total: string}>
     */
    private function linesForConcern(array $concern): array
    {
        $fromGroups = collect($concern['work_groups'] ?? [])
            ->flatMap(fn (mixed $group): array => is_array($group) ? ($group['lines'] ?? []) : []);

        $source = $fromGroups->isNotEmpty() ? $fromGroups : collect($concern['lines'] ?? []);

        return $source
            ->filter(fn (mixed $line): bool => is_array($line) && ($line['type'] ?? '') !== 'note')
            ->map(fn (array $line): array => [
                'description' => (string) ($line['customer_description'] ?? $line['description'] ?? ''),
                'quantity' => (string) ($line['quantity'] ?? '1'),
                'total' => (string) ($line['total'] ?? $line['line_total'] ?? $line['sell'] ?? $line['subtotal'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|iterable<int, array<string, mixed>>  $rows
     * @return list<array{url: string, caption: string}>
     */
    private function photoRows(iterable $rows): array
    {
        return collect($rows)
            ->filter(fn (array $row): bool => filled($row['url'] ?? null))
            ->map(fn (array $row): array => [
                'url' => (string) $row['url'],
                'caption' => (string) ($row['caption'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
