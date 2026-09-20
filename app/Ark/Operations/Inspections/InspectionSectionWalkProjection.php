<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

/**
 * Disposable technician section walk projection over InspectionItem authority.
 */
final class InspectionSectionWalkProjection
{
    /**
     * @param  Collection<int, InspectionItem>  $orderedVisible
     * @param  Collection<int|string, InspectionTemplateItem>  $templateItems
     * @return array<string, mixed>
     */
    public function for(
        RepairOrder $repairOrder,
        Inspection $inspection,
        Collection $orderedVisible,
        Collection $templateItems,
        ?string $workspaceSurface = null,
        ?string $focusSectionKey = null,
    ): array {
        $defaultConditionOptions = InspectionTemplatePointMeta::conditionOptions(null);

        // Resolve road-test gate once for the whole walk (avoid per-point template queries).
        $roadPerformedState = InspectionRoadTestGate::performedObservedState($inspection);

        $pointsBySection = [];
        $unmapped = [];

        foreach ($orderedVisible as $item) {
            $templateItem = $templateItems->get($item->inspection_template_item_id);
            $resolvedTemplate = $templateItem instanceof InspectionTemplateItem ? $templateItem : null;
            $category = (string) ($item->checklist_category_name ?? $item->categoryLabel());
            // Runtime walk_section snapshot owns placement; category only for legacy null snapshots.
            $placement = InspectionPhysicalSectionMap::placementForItem($item);

            $row = $this->pointRow(
                $repairOrder,
                $item,
                $resolvedTemplate,
                $workspaceSurface,
                $roadPerformedState,
            );

            if ($placement === null) {
                $fallbackKey = 'category_'.InspectionFindingLabelCollision::normalize($category);
                $unmapped[$fallbackKey] ??= [
                    'stage_key' => 'checklist',
                    'stage_label' => 'Inspection',
                    'section_key' => $fallbackKey,
                    'section_label' => $category !== '' ? $category : 'General',
                    'optional' => false,
                    'points' => [],
                ];
                $unmapped[$fallbackKey]['points'][] = $row;

                continue;
            }

            $sectionKey = $placement['section_key'];
            $pointsBySection[$sectionKey] ??= [
                'stage_key' => $placement['stage_key'],
                'stage_label' => $placement['stage_label'],
                'section_key' => $sectionKey,
                'section_label' => $placement['section_label'],
                'optional' => $placement['optional'],
                'points' => [],
            ];
            $pointsBySection[$sectionKey]['points'][] = $row;
        }

        $stages = [];
        foreach (InspectionPhysicalSectionMap::standardStages() as $stageDef) {
            $sections = [];
            foreach ($stageDef['sections'] as $sectionDef) {
                $bucket = $pointsBySection[$sectionDef['key']] ?? null;
                if ($bucket === null || $bucket['points'] === []) {
                    continue;
                }
                $sections[] = $this->finalizeSection($bucket);
            }

            if ($sections === []) {
                continue;
            }

            $stages[] = [
                'key' => $stageDef['key'],
                'label' => $stageDef['label'],
                'optional' => $stageDef['optional'],
                'hint' => $stageDef['optional']
                    ? 'Only when the concern, diagnosis, or verification needs it — not required for every visit.'
                    : null,
                'sections' => $sections,
                'state' => $this->aggregateState($sections),
                'addressed' => array_sum(array_column($sections, 'addressed')),
                'total' => array_sum(array_column($sections, 'total')),
            ];
        }

        if ($unmapped !== []) {
            $sections = array_map(fn (array $bucket): array => $this->finalizeSection($bucket), array_values($unmapped));
            $stages[] = [
                'key' => 'checklist',
                'label' => 'Inspection',
                'optional' => false,
                'hint' => null,
                'sections' => $sections,
                'state' => $this->aggregateState($sections),
                'addressed' => array_sum(array_column($sections, 'addressed')),
                'total' => array_sum(array_column($sections, 'total')),
            ];
        }

        $addressed = $orderedVisible
            ->filter(function (InspectionItem $item) use ($templateItems): bool {
                $tpl = $templateItems->get($item->inspection_template_item_id);

                return InspectionPointCompletion::isAddressed(
                    $item,
                    $tpl instanceof InspectionTemplateItem ? $tpl : null,
                );
            })
            ->count();
        $total = $orderedVisible->count();
        $remaining = max(0, $total - $addressed);

        $flatSections = [];
        foreach ($stages as $stage) {
            foreach ($stage['sections'] as $section) {
                $flatSections[] = $section;
            }
        }

        $focusKey = $focusSectionKey;
        if ($focusKey === null || ! collect($flatSections)->contains(fn (array $s): bool => $s['key'] === $focusKey)) {
            $firstIncomplete = collect($flatSections)->first(
                fn (array $s): bool => in_array($s['state'], ['not_started', 'in_progress'], true)
            );
            $focusKey = $firstIncomplete['key'] ?? ($flatSections[0]['key'] ?? null);
        }

        return [
            'stages' => $stages,
            'sections' => $flatSections,
            'focus_section_key' => $focusKey,
            'progress' => [
                'addressed' => $addressed,
                'total' => $total,
                'remaining' => $remaining,
            ],
            'condition_options' => $defaultConditionOptions,
            'rear_axle_brake_type' => $inspection->rear_axle_brake_type,
            'csrf' => csrf_token(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pointRow(
        RepairOrder $repairOrder,
        InspectionItem $item,
        ?InspectionTemplateItem $templateItem,
        ?string $workspaceSurface,
        ?InspectionObservedState $roadPerformedState,
    ): array {
        $item->loadMissing(['measurements', 'photos']);

        $status = $item->observed_state instanceof InspectionObservedState
            ? InspectionChecklistStatus::fromObservedState($item->observed_state)
            : null;

        $slots = InspectionMeasurementSlots::fromTemplateItem($templateItem);
        $slotValues = [];
        foreach ($slots as $slot) {
            $row = $item->measurements->first(
                fn (InspectionItemMeasurement $m): bool => strcasecmp((string) $m->name, $slot['name']) === 0
                    || strcasecmp((string) $m->name, $slot['key']) === 0,
            );
            $slotValues[] = [
                'key' => $slot['key'],
                'name' => $slot['name'],
                'unit' => $slot['unit'],
                'required' => $slot['required'],
                'type' => $slot['type'],
                'value' => $row instanceof InspectionItemMeasurement ? (string) $row->value : '',
            ];
        }

        $addressed = InspectionPointCompletion::isAddressed($item, $templateItem);
        $missingSlots = InspectionPointCompletion::missingRequiredSlotNames($item, $templateItem);
        $gateGroup = $templateItem?->gate_group;
        $isAxleGate = $gateGroup === 'axle_gate';
        $isRoadFinding = $gateGroup === 'road_test_findings';
        $findingLocked = $isRoadFinding && $roadPerformedState === InspectionObservedState::NotChecked;
        $findingForceNa = $isRoadFinding && $roadPerformedState === InspectionObservedState::Na;

        $meta = InspectionTemplatePointMeta::from($templateItem);
        $options = InspectionTemplatePointMeta::conditionOptions($templateItem);
        if ($findingForceNa) {
            $options = array_values(array_filter(
                $options,
                fn (array $option): bool => ($option['value'] ?? '') === 'na',
            ));
        }

        $observationOptions = InspectionTemplatePointMeta::observationOptions($templateItem);
        $selectedObservations = is_array($item->selected_observations)
            ? array_values(array_map('strval', $item->selected_observations))
            : [];

        $note = filled($item->notes) ? (string) $item->notes : null;
        $photoCount = $item->photos->count();
        $shouldExpandStatus = InspectionTemplatePointMeta::shouldExpandForStatus($templateItem, $status);
        $needsExpand = filled($note)
            || $photoCount > 0
            || $slots !== []
            || $selectedObservations !== []
            || $isAxleGate
            || $findingLocked
            || $shouldExpandStatus;

        $photoStoreUrl = route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item]);
        if ($workspaceSurface === InspectionWorkspaceUrl::SURFACE_TABLET) {
            $photoStoreUrl .= (str_contains($photoStoreUrl, '?') ? '&' : '?').'surface=tablet';
        }

        return [
            'id' => $item->id,
            'label' => $item->label,
            'category_name' => (string) ($item->checklist_category_name ?? $item->categoryLabel()),
            'point_key' => $templateItem?->point_key,
            'group' => $meta['group'] ?? null,
            'corner' => $meta['corner'] ?? null,
            'status' => $status?->value,
            'status_label' => InspectionTemplatePointMeta::statusDisplayLabel($templateItem, $status) ?? '—',
            'addressed' => $addressed,
            'missing_measurement_slots' => $missingSlots,
            'measurement_slots' => $slotValues,
            'note' => $note,
            'selected_observations' => $selectedObservations,
            'observation_options' => $observationOptions,
            'expand_when' => InspectionTemplatePointMeta::expandWhen($templateItem),
            'photo_count' => $photoCount,
            'photos' => $item->photos->take(4)->map(fn (InspectionItemPhoto $photo): array => [
                'id' => $photo->id,
                'url' => InspectionEvidenceUrls::show($repairOrder, $photo, 'web'),
                'is_image' => $photo->isImage(),
                'is_video' => $photo->isVideo(),
            ])->values()->all(),
            'is_axle_gate' => $isAxleGate,
            'gate_group' => $gateGroup,
            'road_test_finding_locked' => $findingLocked,
            'road_test_force_na' => $findingForceNa,
            'condition_options' => $options,
            'condition_palette' => InspectionTemplatePointMeta::conditionPalette($templateItem),
            'needs_expand_default' => ($needsExpand && ! $addressed) || $shouldExpandStatus,
            'update_url' => route('operations.repair-orders.inspection.points.update', [
                'repairOrder' => $repairOrder,
                'item' => $item,
            ]),
            'deep_url' => InspectionWorkspaceUrl::point($repairOrder, $item->id, $workspaceSurface),
            'photo_store_url' => $photoStoreUrl,
            // Brake comparison prompts stay on the deep point surface to keep section GET lean.
            'brake_prompts' => [],
        ];
    }

    /**
     * @param  array{
     *     stage_key: string,
     *     stage_label: string,
     *     section_key: string,
     *     section_label: string,
     *     optional: bool,
     *     points: list<array<string, mixed>>
     * }  $bucket
     * @return array<string, mixed>
     */
    private function finalizeSection(array $bucket): array
    {
        $points = $bucket['points'];
        $total = count($points);
        $addressed = count(array_filter($points, fn (array $p): bool => (bool) ($p['addressed'] ?? false)));

        $state = match (true) {
            $addressed <= 0 => 'not_started',
            $addressed >= $total => 'complete',
            default => 'in_progress',
        };

        return [
            'key' => $bucket['section_key'],
            'label' => $bucket['section_label'],
            'stage_key' => $bucket['stage_key'],
            'optional' => $bucket['optional'],
            'state' => $state,
            'state_label' => match ($state) {
                'complete' => 'Complete',
                'in_progress' => 'In progress',
                default => 'Not started',
            },
            'addressed' => $addressed,
            'total' => $total,
            'points' => $points,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function aggregateState(array $sections): string
    {
        $total = array_sum(array_column($sections, 'total'));
        $addressed = array_sum(array_column($sections, 'addressed'));

        return match (true) {
            $total <= 0, $addressed <= 0 => 'not_started',
            $addressed >= $total => 'complete',
            default => 'in_progress',
        };
    }
}
