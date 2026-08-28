<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ApplyInspectionTemplateAction
{
    public function execute(
        RepairOrder $repairOrder,
        Inspection $inspection,
        ?InspectionTemplate $template = null,
        ?User $actor = null,
    ): int {
        $template ??= ResolveRequiredInspectionTemplate::for($repairOrder);

        if (! $template instanceof InspectionTemplate || ! $template->enabled || $template->isArchived()) {
            return 0;
        }

        $template->loadMissing(['categories.items']);

        return DB::transaction(function () use ($repairOrder, $inspection, $template, $actor): int {
            if ($inspection->inspection_template_id !== null
                && (int) $inspection->inspection_template_id !== (int) $template->id
                && $inspection->hasCapturedEvidence()) {
                // Bound: do not destroy historical evidence by silent template replace.
                return 0;
            }

            if ($inspection->inspection_template_id !== null
                && (int) $inspection->inspection_template_id !== (int) $template->id
                && ! $inspection->hasCapturedEvidence()) {
                // Empty active checklist only — superseded history stays on the Inspection.
                $inspection->items()
                    ->whereNull('superseded_at')
                    ->each(function (InspectionItem $item): void {
                        $item->measurements()->delete();
                        $item->photos()->delete();
                        $item->delete();
                    });
                $inspection->forceFill([
                    'rear_axle_brake_type' => null,
                ])->save();
            }

            if ($repairOrder->required_inspection_template_id === null) {
                $repairOrder->forceFill([
                    'required_inspection_template_id' => $template->id,
                ])->save();
            }

            $inspection->forceFill([
                'inspection_template_id' => $template->id,
                'recorded_by_user_id' => $actor?->id ?? $inspection->recorded_by_user_id,
                'started_at' => $inspection->started_at ?? now(),
            ])->save();

            $existingTemplateItemIds = $inspection->items()
                ->whereNull('superseded_at')
                ->whereNotNull('inspection_template_item_id')
                ->pluck('inspection_template_item_id')
                ->all();

            $nextPosition = (int) $inspection->items()->max('position');
            $now = now();
            $rows = [];

            foreach ($template->categories as $category) {
                foreach ($category->items as $templateItem) {
                    if (! $templateItem->enabled) {
                        continue;
                    }

                    if (in_array($templateItem->id, $existingTemplateItemIds, true)) {
                        continue;
                    }

                    $nextPosition++;

                    $rows[] = [
                        'inspection_id' => $inspection->id,
                        'checklist_category_name' => $category->name,
                        // Snapshot Builder walk placement at apply — walk must not re-read live Builder.
                        'walk_section' => InspectionTemplatePointMeta::walkSection($templateItem),
                        'category' => InspectionItemCategory::General->value,
                        'label' => $templateItem->label,
                        'observed_state' => InspectionObservedState::NotChecked->value,
                        'position' => $nextPosition,
                        'inspection_template_item_id' => $templateItem->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                InspectionItem::query()->insert($rows);
            }

            return count($rows);
        });
    }
}
