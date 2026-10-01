<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final class UpdateInspectionChecklistItemAction
{
    use ValidatesInspectionScope;

    public function __construct(
        private readonly InspectionEvidenceStore $evidenceStore,
    ) {}

    /**
     * @param  list<array{key?: string, name?: string, value: string, unit?: ?string}>|null  $measurements
     * @param  list<string>|null  $selectedObservations
     * @return array{
     *     item: InspectionItem,
     *     requires_photo: bool,
     *     requires_measurement: bool,
     *     missing_measurement_slots: list<string>,
     *     addressed: bool,
     *     brake_prompts: list<array<string, mixed>>,
     * }
     */
    public function execute(
        RepairOrder $repairOrder,
        Inspection $inspection,
        InspectionItem $item,
        InspectionChecklistStatus $status,
        ?User $actor = null,
        ?string $notes = null,
        ?string $measurementValue = null,
        ?string $measurementUnit = null,
        ?UploadedFile $photo = null,
        ?array $measurements = null,
        ?string $rearAxleBrakeType = null,
        ?array $selectedObservations = null,
    ): array {
        $item = $this->itemForRepairOrder($repairOrder, $item);
        $item->loadMissing(['measurements', 'photos', 'inspection']);

        $templateItem = $item->inspection_template_item_id !== null
            ? InspectionTemplateItem::query()->find($item->inspection_template_item_id)
            : null;

        if ($templateItem instanceof InspectionTemplateItem) {
            InspectionRoadTestGate::assertFindingStatusAllowed($inspection, $templateItem, $status);

            if (($templateItem->gate_group ?? null) === 'axle_gate' && filled($rearAxleBrakeType)) {
                $type = strtolower(trim($rearAxleBrakeType));
                if (! in_array($type, [Inspection::REAR_AXLE_DISC, Inspection::REAR_AXLE_DRUM], true)) {
                    abort(422, 'Rear axle type must be Disc or Drum.');
                }
                $inspection->forceFill(['rear_axle_brake_type' => $type])->save();
            }
        }

        $slots = InspectionMeasurementSlots::fromTemplateItem($templateItem);

        $requiresPhoto = InspectionTemplatePointMeta::photoRequiredForStatus($templateItem, $status)
            || ((bool) ($templateItem?->requires_scan_evidence ?? false));

        $updates = [
            'observed_state' => $status->toObservedState()->value,
        ];

        if ($notes !== null) {
            $updates['notes'] = $notes;
        }

        if ($selectedObservations !== null) {
            $allowed = collect(InspectionTemplatePointMeta::observationOptions($templateItem))
                ->pluck('key')
                ->all();
            $updates['selected_observations'] = array_values(array_filter(
                $selectedObservations,
                fn (mixed $key): bool => is_string($key) && ($allowed === [] || in_array($key, $allowed, true)),
            ));
        }

        $item->update($updates);

        if (is_array($measurements)) {
            $this->upsertMeasurements($item, $templateItem, $measurements, $slots);
        } elseif (filled($measurementValue)) {
            $primary = $slots[0] ?? [
                'key' => 'primary',
                'name' => $templateItem?->measurement_name ?? 'Measurement',
                'unit' => $templateItem?->measurement_unit,
            ];
            $this->upsertMeasurements($item, $templateItem, [[
                'key' => $primary['key'] ?? 'primary',
                'name' => $primary['name'] ?? 'Measurement',
                'value' => $measurementValue,
                'unit' => $measurementUnit ?? ($primary['unit'] ?? null),
            ]], $slots);
        }

        if ($photo instanceof UploadedFile) {
            $this->evidenceStore->store(
                $repairOrder,
                $item,
                $photo,
                $actor,
            );
        }

        $this->touchInspectionRecorded($inspection, $actor);

        $item->refresh()->load(['measurements', 'photos']);
        $inspection->refresh();

        $missingSlots = InspectionPointCompletion::missingRequiredSlotNames($item, $templateItem);
        $addressed = InspectionPointCompletion::isAddressed($item, $templateItem);
        $missingPhoto = $requiresPhoto && $item->photos->isEmpty()
            && ! (filled($item->notes) && ($templateItem?->requires_scan_evidence ?? false));

        return [
            'item' => $item,
            'requires_photo' => $missingPhoto,
            'requires_measurement' => $missingSlots !== [],
            'missing_measurement_slots' => $missingSlots,
            'addressed' => $addressed,
            'brake_prompts' => InspectionBrakeComparison::promptsForItem($item, $inspection),
        ];
    }

    /**
     * @param  list<array{key?: string, name?: string, value: string, unit?: ?string}>  $measurements
     * @param  list<array{key: string, name: string, unit: ?string, required: bool, type: string}>  $slots
     */
    private function upsertMeasurements(
        InspectionItem $item,
        ?InspectionTemplateItem $templateItem,
        array $measurements,
        array $slots,
    ): void {
        $item->loadMissing('measurements');
        $position = (int) $item->measurements()->max('position');

        foreach ($measurements as $payload) {
            $value = trim((string) ($payload['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $key = (string) ($payload['key'] ?? '');
            $name = (string) ($payload['name'] ?? '');
            $slot = collect($slots)->first(function (array $candidate) use ($key, $name): bool {
                return ($key !== '' && ($candidate['key'] ?? '') === $key)
                    || ($name !== '' && strcasecmp((string) ($candidate['name'] ?? ''), $name) === 0);
            });

            $resolvedName = $name !== ''
                ? $name
                : (string) ($slot['name'] ?? $templateItem?->measurement_name ?? 'Measurement');
            $resolvedUnit = $this->resolveMeasurementUnit(
                is_array($slot) ? $slot : null,
                $templateItem,
                array_key_exists('unit', $payload) ? $payload['unit'] : null,
            );

            $existing = $item->measurements->first(
                fn (InspectionItemMeasurement $row): bool => strcasecmp((string) $row->name, $resolvedName) === 0
                    || ($key !== '' && strcasecmp((string) $row->name, $key) === 0),
            );

            if ($existing instanceof InspectionItemMeasurement) {
                $existing->update([
                    'name' => $resolvedName,
                    'value' => $value,
                    'unit' => $resolvedUnit,
                ]);
                continue;
            }

            $position++;
            $created = $item->measurements()->create([
                'name' => $resolvedName,
                'value' => $value,
                'unit' => $resolvedUnit,
                'position' => $position,
            ]);
            $item->setRelation('measurements', $item->measurements->push($created));
        }
    }

    /**
     * Template-defined slots keep their unit even when the client omits or clears unit.
     * Freeform / unknown units stay null.
     *
     * @param  array{key?: string, name?: string, unit?: ?string}|null  $slot
     */
    private function resolveMeasurementUnit(
        ?array $slot,
        ?InspectionTemplateItem $templateItem,
        mixed $payloadUnit,
    ): ?string {
        if (filled($payloadUnit)) {
            return trim((string) $payloadUnit);
        }

        $slotUnit = is_array($slot) && array_key_exists('unit', $slot) ? $slot['unit'] : null;
        if (filled($slotUnit)) {
            return trim((string) $slotUnit);
        }

        $legacyUnit = $templateItem?->measurement_unit;
        if (filled($legacyUnit)) {
            return trim((string) $legacyUnit);
        }

        return null;
    }
}
