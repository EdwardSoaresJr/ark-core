<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Request;

final class InspectionWalkWorkspaceProjection
{
    public function __construct(
        private readonly ApplyInspectionTemplateAction $applyTemplate,
        private readonly InspectionItemLivingRecordProjection $livingRecord,
        private readonly InspectionSectionWalkProjection $sectionWalk,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Request $request, RepairOrder $repairOrder, Inspection $inspection): array
    {
        DefaultInspectionTemplateCatalog::seedIfMissing();

        $workspaceSurface = InspectionWorkspaceUrl::normalizeSurface($request->query('surface'));
        $wantsPointMode = $request->query('point') !== null && (int) $request->query('point') > 0
            || $request->query('view') === 'point';

        $hasChecklistItems = $inspection->items()
            ->whereNull('superseded_at')
            ->whereNotNull('inspection_template_item_id')
            ->exists();

        if (! $hasChecklistItems) {
            $this->applyTemplate->execute($repairOrder, $inspection, actor: $request->user());
            $inspection->refresh();
        }

        $inspection->load([
            'items.measurements',
            'items.photos',
            'items.concern',
            'template',
        ]);

        $repairOrder->loadMissing('concerns');

        $ordered = InspectionWalkVisibility::visibleItems(
            $inspection,
            InspectionChecklistItems::orderedChecklistItems($inspection),
        );

        $base = [
            'inspection' => $inspection,
            'walk_enabled' => false,
            'walk_mode' => 'empty',
            'walk_points' => [],
            'living_record' => null,
            'section_walk' => null,
            'condition_options' => [],
            'progress' => [
                'checked' => 0,
                'total' => 0,
                'remaining' => 0,
            ],
            'concerns' => $repairOrder->concerns,
            'finding_intents' => InspectionFindingIntent::ordered(),
            'workspace_surface' => $workspaceSurface,
            'is_tablet_surface' => $workspaceSurface === InspectionWorkspaceUrl::SURFACE_TABLET,
            'template_name' => $inspection->template?->name
                ?? ResolveRequiredInspectionTemplate::for($repairOrder)?->name,
            'sections_url' => InspectionWorkspaceUrl::show($repairOrder, array_filter([
                'surface' => $workspaceSurface,
            ])),
        ];

        if ($ordered->isEmpty()) {
            return $base;
        }

        $templateItems = InspectionTemplateItem::query()
            ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $checkedCount = $ordered
            ->filter(function (InspectionItem $item) use ($templateItems): bool {
                $tpl = $templateItems->get($item->inspection_template_item_id);

                return InspectionPointCompletion::isAddressed(
                    $item,
                    $tpl instanceof InspectionTemplateItem ? $tpl : null,
                );
            })
            ->count();

        $conditionOptions = collect(InspectionChecklistStatus::ordered())
            ->map(fn (InspectionChecklistStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'display' => $status->label(),
            ])
            ->all();

        $progress = [
            'checked' => $checkedCount,
            'total' => $ordered->count(),
            'remaining' => max(0, $ordered->count() - $checkedCount),
        ];

        $walkPoints = $ordered
            ->map(function (InspectionItem $item) use ($repairOrder, $workspaceSurface, $templateItems): array {
                $tpl = $templateItems->get($item->inspection_template_item_id);

                return [
                    'id' => $item->id,
                    'label' => $item->label,
                    'category_name' => $item->checklist_category_name,
                    'checked' => InspectionPointCompletion::isAddressed(
                        $item,
                        $tpl instanceof InspectionTemplateItem ? $tpl : null,
                    ),
                    'is_current' => false,
                    'url' => InspectionWorkspaceUrl::point($repairOrder, $item->id, $workspaceSurface),
                ];
            })
            ->all();

        $shared = array_merge($base, [
            'walk_enabled' => true,
            'walk_points' => $walkPoints,
            'condition_options' => $conditionOptions,
            'progress' => $progress,
            'photo_purposes' => [
                InspectionPhotoPurpose::Internal,
                InspectionPhotoPurpose::Customer,
                InspectionPhotoPurpose::Before,
                InspectionPhotoPurpose::After,
            ],
        ]);

        if ($wantsPointMode) {
            $currentItem = $this->resolveCurrentItem($request, $ordered, $templateItems);
            $templateItem = $currentItem?->inspection_template_item_id !== null
                ? $templateItems->get($currentItem->inspection_template_item_id)
                : null;

            $livingRecord = $currentItem instanceof InspectionItem
                ? $this->livingRecord->forItem(
                    $repairOrder,
                    $currentItem,
                    $templateItem instanceof InspectionTemplateItem ? $templateItem : null,
                    $ordered,
                    'web',
                    $workspaceSurface,
                )
                : null;

            $shared['walk_points'] = collect($walkPoints)
                ->map(function (array $point) use ($currentItem): array {
                    $point['is_current'] = $currentItem?->id === $point['id'];

                    return $point;
                })
                ->all();

            return array_merge($shared, [
                'walk_mode' => 'point',
                'living_record' => $livingRecord,
                'section_walk' => null,
            ]);
        }

        $focusSection = is_string($request->query('section'))
            ? trim((string) $request->query('section'))
            : null;

        $sectionWalk = $this->sectionWalk->for(
            $repairOrder,
            $inspection,
            $ordered,
            $templateItems,
            $workspaceSurface,
            $focusSection !== '' ? $focusSection : null,
        );

        return array_merge($shared, [
            'walk_mode' => 'sections',
            'living_record' => null,
            'section_walk' => $sectionWalk,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, InspectionItem>  $ordered
     * @param  \Illuminate\Support\Collection<int|string, InspectionTemplateItem>  $templateItems
     */
    private function resolveCurrentItem(Request $request, $ordered, $templateItems): ?InspectionItem
    {
        $pointId = (int) $request->query('point', 0);

        if ($pointId > 0) {
            $selected = $ordered->firstWhere('id', $pointId);

            if ($selected instanceof InspectionItem) {
                return $selected;
            }
        }

        $nextUnchecked = $ordered->first(function (InspectionItem $item) use ($templateItems): bool {
            $tpl = $templateItems->get($item->inspection_template_item_id);

            return ! InspectionPointCompletion::isAddressed(
                $item,
                $tpl instanceof InspectionTemplateItem ? $tpl : null,
            );
        });

        return $nextUnchecked instanceof InspectionItem
            ? $nextUnchecked
            : $ordered->first();
    }
}
