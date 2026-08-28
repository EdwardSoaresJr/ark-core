<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Documents\CustomerFacingEstimateStatus;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Evidence\Evidence;
use App\Ark\Operations\Evidence\EvidenceProjection;
use App\Ark\Operations\Evidence\RecordEvidenceCustomerPresentedAction;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Carbon;

/**
 * Status-first Repair Portal home. Estimate is a card — not the page.
 */
final class RepairPortalHubProjection
{
    public function __construct(
        private readonly PortalEstimateSnapshot $estimateSnapshot,
        private readonly EvidenceProjection $evidence,
        private readonly CustomerFacingEstimateStatus $estimateStatus,
        private readonly RecordEvidenceCustomerPresentedAction $recordEvidencePresented,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAccess(RepairOrderPortalAccess $access, string $publicCode, bool $recordCustomerView): array
    {
        $repairOrder = $access->repairOrder()
            ->with(['customer', 'vehicle', 'concerns'])
            ->firstOrFail();

        $snapshot = $this->estimateSnapshot->forRepairOrder($repairOrder);

        $sharedEvidence = $this->evidence
            ->forRepairOrder($repairOrder, customerFacing: true)
            ->map(function (array $row) use ($publicCode): array {
                $row['url'] = route('portal.repair.evidence.show', [
                    'code' => $publicCode,
                    'evidence' => $row['id'],
                ]);

                return $row;
            });

        if ($recordCustomerView && $sharedEvidence->isNotEmpty()) {
            $presented = Evidence::query()
                ->whereIn('id', $sharedEvidence->pluck('id')->all())
                ->get();
            $this->recordEvidencePresented->handle($repairOrder, $presented);
        }

        if ($recordCustomerView && $access->first_customer_viewed_at === null) {
            $access->forceFill(['first_customer_viewed_at' => now()])->save();
        }

        $estimateUpdatedAt = $this->estimateUpdatedAt($repairOrder);
        $vehicle = $repairOrder->vehicle;
        $vehicleLine = trim(implode(' ', array_filter([
            $vehicle?->year,
            $vehicle?->make,
            $vehicle?->model,
        ])));

        $inspectionFindingCount = InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder);
        $inspectionReady = $inspectionFindingCount > 0;

        return [
            'access' => $access,
            'repair_order' => $repairOrder,
            'public_code' => $publicCode,
            'vehicle_line' => $vehicleLine !== '' ? $vehicleLine : 'Your vehicle',
            'plate' => $vehicle?->plate,
            'status_label' => $this->estimateStatus->labelForRepairOrder($repairOrder),
            'status_detail' => $repairOrder->statusDisplayLabel(),
            'estimate_updated_label' => $estimateUpdatedAt !== null
                ? 'Updated '.$estimateUpdatedAt->diffForHumans()
                : null,
            'estimate_total' => data_get($snapshot, 'totals.total'),
            'snapshot' => $snapshot,
            'shared_evidence' => $sharedEvidence,
            'photo_count' => $sharedEvidence->where('is_image', true)->count(),
            'video_count' => $sharedEvidence->where('is_video', true)->count(),
            'shop' => $snapshot['shop'] ?? [],
            'concerns' => \App\Ark\Operations\RepairOrders\RecommendationIntent::sortedSnapshotConcerns($snapshot['concerns'] ?? []),
            'inspection' => [
                'ready' => $inspectionReady,
                'finding_count' => $inspectionFindingCount,
                'url' => $inspectionReady
                    ? route('portal.repair.inspection.show', ['code' => $publicCode])
                    : null,
            ],
        ];
    }

    private function estimateUpdatedAt(RepairOrder $repairOrder): ?Carbon
    {
        $document = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', 'estimate')
            ->first();

        $at = $document?->updated_at ?? $repairOrder->updated_at;

        return $at instanceof Carbon ? $at : null;
    }
}
