<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

/**
 * Technician working object for one inspection point — condition and evidence live here.
 */
final class InspectionItemLivingRecordProjection
{
    public function __construct(
        private readonly InspectionItemPriorVisitHistory $priorVisitHistory,
    ) {}

    /**
     * @param  Collection<int, InspectionItem>|null  $orderedChecklistItems
     * @return array<string, mixed>
     */
    public function forItem(
        RepairOrder $repairOrder,
        InspectionItem $item,
        ?InspectionTemplateItem $templateItem = null,
        ?Collection $orderedChecklistItems = null,
        string $evidenceSurface = 'web',
        ?string $workspaceSurface = null,
    ): array {
        $item->loadMissing(['measurements', 'photos', 'concern']);

        $status = $item->observed_state instanceof InspectionObservedState
            ? InspectionChecklistStatus::fromObservedState($item->observed_state)
            : null;

        $measurement = $item->measurements->first();
        $priorVisits = $this->priorVisitHistory->forItem($repairOrder, $item);
        $slots = InspectionMeasurementSlots::fromTemplateItem($templateItem);
        $inspection = $item->inspection ?? Inspection::query()->find($item->inspection_id);
        $gateGroup = $templateItem?->gate_group;
        $addressed = InspectionPointCompletion::isAddressed($item, $templateItem);
        $missingSlots = InspectionPointCompletion::missingRequiredSlotNames($item, $templateItem);
        $brakePrompts = $inspection instanceof Inspection
            ? InspectionBrakeComparison::promptsForItem($item, $inspection)
            : [];
        $findingLocked = $inspection instanceof Inspection
            ? InspectionRoadTestGate::findingLocked($inspection, $templateItem)
            : false;
        $findingForceNa = $inspection instanceof Inspection
            ? InspectionRoadTestGate::findingForceNa($inspection, $templateItem)
            : false;

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

        return [
            'id' => $item->id,
            'label' => $item->label,
            'category_name' => $item->checklist_category_name ?? $item->categoryLabel(),
            'point_key' => $templateItem?->point_key,
            'gate_group' => $gateGroup,
            'axle_role' => $templateItem?->axle_role,
            'status' => $status?->value,
            'status_label' => InspectionTemplatePointMeta::statusDisplayLabel($templateItem, $status) ?? $status?->label(),
            'status_display' => InspectionTemplatePointMeta::statusDisplayLabel($templateItem, $status)
                ?? $this->statusDisplay($status),
            'condition_options' => InspectionTemplatePointMeta::conditionOptions($templateItem),
            'condition_palette' => InspectionTemplatePointMeta::conditionPalette($templateItem),
            'checked' => $addressed,
            'addressed' => $addressed,
            'missing_measurement_slots' => $missingSlots,
            'concern' => $item->concern !== null
                ? [
                    'id' => $item->concern->id,
                    'title' => $item->concern->summary,
                ]
                : null,
            'note' => filled($item->notes) ? (string) $item->notes : null,
            'selected_observations' => is_array($item->selected_observations)
                ? array_values(array_map('strval', $item->selected_observations))
                : [],
            'observation_options' => InspectionTemplatePointMeta::observationOptions($templateItem),
            'expand_when' => InspectionTemplatePointMeta::expandWhen($templateItem),
            'requires_photo' => $status instanceof InspectionChecklistStatus
                ? InspectionTemplatePointMeta::photoRequiredForStatus($templateItem, $status)
                : (bool) ($templateItem?->requires_photo ?? false),
            'requires_scan_evidence' => (bool) ($templateItem?->requires_scan_evidence ?? false),
            'measurement_name' => $templateItem?->measurement_name ?? ($slots[0]['name'] ?? null),
            'measurement_unit' => $templateItem?->measurement_unit ?? ($slots[0]['unit'] ?? null),
            'measurement_slots' => $slotValues,
            'is_axle_gate' => $gateGroup === 'axle_gate',
            'rear_axle_brake_type' => $inspection instanceof Inspection ? $inspection->rear_axle_brake_type : null,
            'road_test_finding_locked' => $findingLocked,
            'road_test_force_na' => $findingForceNa,
            'brake_prompts' => $brakePrompts,
            'photos' => $item->photos->map(function (InspectionItemPhoto $photo) use ($repairOrder, $evidenceSurface): array {
                $destroyUrl = null;
                if ($evidenceSurface !== 'mobile') {
                    $destroyUrl = route('operations.repair-orders.inspection.photos.destroy', [
                        'repairOrder' => $repairOrder,
                        'photo' => $photo,
                    ]);
                }

                return [
                    'id' => $photo->id,
                    'url' => InspectionEvidenceUrls::show($repairOrder, $photo, $evidenceSurface),
                    'is_image' => $photo->isImage(),
                    'is_video' => $photo->isVideo(),
                    'content_type' => $photo->content_type,
                    'destroy_url' => $destroyUrl,
                ];
            })->values()->all(),
            'measurements' => $item->measurements->map(fn (InspectionItemMeasurement $measurement): array => [
                'id' => $measurement->id,
                'name' => $measurement->name,
                'value' => $measurement->value,
                'unit' => $measurement->unit,
                'formatted' => $measurement->formattedValue(),
            ])->values()->all(),
            'measurement' => $measurement instanceof InspectionItemMeasurement
                ? [
                    'value' => $measurement->value,
                    'unit' => $measurement->unit,
                    'formatted' => $measurement->formattedValue(),
                ]
                : null,
            'recommendation_hint' => $this->recommendationHint($item, $status, $measurement),
            'prior_visits' => $priorVisits,
            'navigation' => $this->navigation($repairOrder, $item, $orderedChecklistItems, $workspaceSurface),
            'update_url' => match ($evidenceSurface) {
                'mobile' => route('api.mobile.repair-orders.inspection-checklist.items.update', [
                    'repairOrder' => $repairOrder,
                    'item' => $item,
                ]),
                default => route('operations.repair-orders.inspection.points.update', [
                    'repairOrder' => $repairOrder,
                    'item' => $item,
                ]),
            },
        ];
    }

    private function statusDisplay(?InspectionChecklistStatus $status): ?string
    {
        return match ($status) {
            null => null,
            default => $status->label(),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recommendationHint(
        InspectionItem $item,
        ?InspectionChecklistStatus $status,
        mixed $measurement,
    ): ?array {
        if ($status === null) {
            return null;
        }

        $needsRecommendation = in_array($status, [
            InspectionChecklistStatus::NeedsAttention,
            InspectionChecklistStatus::Failed,
        ], true);

        if (! $needsRecommendation) {
            return null;
        }

        if (! $measurement instanceof InspectionItemMeasurement) {
            return [
                'available' => false,
                'label' => 'Add a measurement to draft a recommendation',
                'advisor_review_required' => true,
            ];
        }

        $intent = InspectionFindingIntent::tryFromNotes($item->notes) ?? InspectionFindingIntent::Safety;
        $summary = trim($item->label.' · '.$intent->label().' · '.$measurement->formattedValue());

        return [
            'available' => true,
            'label' => 'Recommendation draft available',
            'summary' => $summary,
            'intent' => $intent->value,
            'intent_label' => $intent->label(),
            'advisor_review_required' => true,
        ];
    }

    /**
     * @param  Collection<int, InspectionItem>|null  $orderedChecklistItems
     * @return array<string, mixed>
     */
    private function navigation(
        RepairOrder $repairOrder,
        InspectionItem $item,
        ?Collection $orderedChecklistItems,
        ?string $workspaceSurface = null,
    ): array {
        if ($orderedChecklistItems === null || $orderedChecklistItems->isEmpty()) {
            return [
                'index' => null,
                'total' => null,
                'next_item_id' => null,
                'prior_item_id' => null,
                'next_url' => null,
                'prior_url' => null,
            ];
        }

        $index = $orderedChecklistItems->search(fn (InspectionItem $candidate): bool => $candidate->id === $item->id);

        if ($index === false) {
            return [
                'index' => null,
                'total' => $orderedChecklistItems->count(),
                'next_item_id' => null,
                'prior_item_id' => null,
                'next_url' => null,
                'prior_url' => null,
            ];
        }

        $nextItemId = $orderedChecklistItems->get($index + 1)?->id;
        $priorItemId = $orderedChecklistItems->get($index - 1)?->id;
        $surface = InspectionWorkspaceUrl::normalizeSurface($workspaceSurface);

        return [
            'index' => $index + 1,
            'total' => $orderedChecklistItems->count(),
            'next_item_id' => $nextItemId,
            'prior_item_id' => $priorItemId,
            'next_url' => $nextItemId !== null
                ? InspectionWorkspaceUrl::point($repairOrder, $nextItemId, $surface)
                : null,
            'prior_url' => $priorItemId !== null
                ? InspectionWorkspaceUrl::point($repairOrder, $priorItemId, $surface)
                : null,
        ];
    }
}
