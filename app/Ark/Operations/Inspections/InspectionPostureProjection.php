<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Disposable read model: Inspection Posture on a Repair Order.
 *
 * Owns no persistence. Rebuild from inspection checklist authority at any time.
 * Surfaces must consume this — do not re-derive Not Started / % / Complete inline.
 */
final class InspectionPostureProjection
{
    public function forRepairOrder(RepairOrder $repairOrder): InspectionPosture
    {
        $inspection = Inspection::query()
            ->where('repair_order_id', $repairOrder->id)
            ->first();

        $requiredTemplate = ResolveRequiredInspectionTemplate::for($repairOrder);
        $templateName = $requiredTemplate?->name;

        $checked = 0;
        $total = 0;
        $attentionCount = 0;

        if ($inspection instanceof Inspection) {
            $inspection->loadMissing('template');
            if ($inspection->template instanceof InspectionTemplate) {
                $templateName = $inspection->template->name;
            }

            $ordered = InspectionWalkVisibility::visibleItems(
                $inspection,
                InspectionChecklistItems::orderedChecklistItems($inspection),
            );
            $total = $ordered->count();

            $templateItems = InspectionTemplateItem::query()
                ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter()->all())
                ->get()
                ->keyBy('id');

            foreach ($ordered as $item) {
                $templateItem = $templateItems->get($item->inspection_template_item_id);

                if (InspectionPointCompletion::isAddressed(
                    $item,
                    $templateItem instanceof InspectionTemplateItem ? $templateItem : null,
                )) {
                    $checked++;
                }

                if ($this->isAttentionFinding($item)) {
                    $attentionCount++;
                }
            }
        }

        $started = $checked > 0
            || ($inspection instanceof Inspection && $inspection->items()
                ->whereNull('superseded_at')
                ->where('observed_state', '!=', InspectionObservedState::NotChecked->value)
                ->exists());

        $remaining = max(0, $total - $checked);
        $percent = $total > 0 ? (int) floor(($checked / $total) * 100) : null;

        [$key, $headline, $detail] = $this->classify(
            started: $started,
            checked: $checked,
            total: $total,
            remaining: $remaining,
            percent: $percent,
            attentionCount: $attentionCount,
        );

        $percentComplete = match ($key) {
            InspectionPosture::IN_PROGRESS => $percent,
            InspectionPosture::COMPLETE, InspectionPosture::NEEDS_REVIEW => $total > 0 ? 100 : null,
            default => null,
        };

        return new InspectionPosture(
            key: $key,
            headline: $headline,
            detail: $detail,
            percentComplete: $percentComplete,
            checked: $checked,
            total: $total,
            remaining: $remaining,
            attentionCount: $attentionCount,
            started: $started,
            templateName: $templateName,
        );
    }

    private function isAttentionFinding(InspectionItem $item): bool
    {
        return in_array($item->observed_state, [
            InspectionObservedState::NeedsAttention,
            InspectionObservedState::Fail,
            InspectionObservedState::Monitor,
        ], true);
    }

    /**
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function classify(
        bool $started,
        int $checked,
        int $total,
        int $remaining,
        ?int $percent,
        int $attentionCount,
    ): array {
        if (! $started) {
            return [InspectionPosture::NOT_STARTED, 'Not Started', null];
        }

        if ($total > 0 && $remaining === 0) {
            if ($attentionCount > 0) {
                $detail = $attentionCount === 1
                    ? '1 finding needs review'
                    : $attentionCount.' findings need review';

                return [InspectionPosture::NEEDS_REVIEW, 'Needs Review', $detail];
            }

            return [InspectionPosture::COMPLETE, 'Complete', $checked.' of '.$total.' checked'];
        }

        $detail = $percent !== null
            ? $percent.'%'
            : null;

        if ($total > 0) {
            $detail = ($detail !== null ? $detail.' · ' : '').$checked.' of '.$total;
        }

        return [InspectionPosture::IN_PROGRESS, 'In Progress', $detail];
    }
}
