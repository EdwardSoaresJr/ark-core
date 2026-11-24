<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Separates coverage completion from evidence / publication readiness.
 * Does not own persistence. Rebuild from inspection authority.
 */
final class InspectionReviewReadinessProjection
{
    public function __construct(
        private readonly InspectionPostureProjection $posture,
    ) {}

    /**
     * @return array{
     *     coverage_key: string,
     *     coverage_checked: int,
     *     coverage_total: int,
     *     coverage_remaining: int,
     *     coverage_complete: bool,
     *     evidence_gap_count: int,
     *     evidence_gaps: list<array{
     *         item_id: int,
     *         label: string,
     *         kind: string,
     *         severity: string,
     *         message: string
     *     }>,
     *     ready_for_internal_review: bool,
     *     ready_for_customer_publication: bool,
     *     publication_blockers: list<string>,
     * }
     */
    public function forRepairOrder(RepairOrder $repairOrder): array
    {
        $posture = $this->posture->forRepairOrder($repairOrder);
        $gaps = $this->collectEvidenceGaps($repairOrder);
        $coverageComplete = $posture->started
            && $posture->remaining === 0
            && in_array($posture->key, [InspectionPosture::COMPLETE, InspectionPosture::NEEDS_REVIEW], true);

        $publicationBlockers = [];
        foreach ($gaps as $gap) {
            if (($gap['severity'] ?? '') === InspectionPointCompletion::SEVERITY_REQUIRED) {
                $publicationBlockers[] = $gap['message'].' ('.$gap['label'].')';
            }
        }

        return [
            'coverage_key' => $posture->key,
            'coverage_checked' => $posture->checked,
            'coverage_total' => $posture->total,
            'coverage_remaining' => $posture->remaining,
            'coverage_complete' => $coverageComplete || (! $posture->started && $posture->total === 0),
            'evidence_gap_count' => count($gaps),
            'evidence_gaps' => $gaps,
            'ready_for_internal_review' => $coverageComplete || ! $posture->started,
            'ready_for_customer_publication' => ($coverageComplete || ! $posture->started) && $publicationBlockers === [],
            'publication_blockers' => $publicationBlockers,
        ];
    }

    /**
     * @return list<array{
     *     item_id: int,
     *     label: string,
     *     kind: string,
     *     severity: string,
     *     message: string
     * }>
     */
    private function collectEvidenceGaps(RepairOrder $repairOrder): array
    {
        $inspection = Inspection::query()
            ->where('repair_order_id', $repairOrder->id)
            ->first();

        if (! $inspection instanceof Inspection) {
            return [];
        }

        $ordered = InspectionWalkVisibility::visibleItems(
            $inspection,
            InspectionChecklistItems::orderedChecklistItems($inspection),
        );

        $templateItems = InspectionTemplateItem::query()
            ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $gaps = [];
        foreach ($ordered as $item) {
            $templateItem = $templateItems->get($item->inspection_template_item_id);
            foreach (InspectionPointCompletion::evidenceGaps(
                $item,
                $templateItem instanceof InspectionTemplateItem ? $templateItem : null,
            ) as $gap) {
                $gaps[] = [
                    'item_id' => (int) $item->id,
                    'label' => (string) $item->label,
                    'kind' => $gap['kind'],
                    'severity' => $gap['severity'],
                    'message' => $gap['message'],
                ];
            }
        }

        return $gaps;
    }
}
