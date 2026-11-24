<?php

namespace App\Ark\Operations\Inspections;

/**
 * Coverage and evidence for one checklist point.
 *
 * Coverage (isAddressed / isCovered): was the point evaluated with required facts?
 * Evidence gaps: supporting photos, scans, and documentation — tracked separately.
 * Photographs do not erase coverage unless builder meta explicitly requires them for coverage.
 */
final class InspectionPointCompletion
{
    public const GAP_PHOTO = 'photo';

    public const GAP_SCAN = 'scan_evidence';

    public const GAP_NOTE = 'note';

    public const GAP_OBSERVATION = 'observation';

    public const GAP_MEASUREMENT = 'measurement';

    public const SEVERITY_REQUIRED = 'required';

    public const SEVERITY_RECOMMENDED = 'recommended';

    /**
     * Coverage alias — factual completion of the inspection point.
     */
    public static function isAddressed(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): bool
    {
        return self::isCovered($item, $templateItem);
    }

    public static function isCovered(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): bool
    {
        $item->loadMissing(['measurements', 'photos']);

        if ($item->observed_state === InspectionObservedState::NotChecked
            || $item->observed_state === null) {
            return false;
        }

        $status = self::statusFor($item);

        if ($status === InspectionChecklistStatus::Na) {
            return true;
        }

        if (! $status instanceof InspectionChecklistStatus) {
            return false;
        }

        $templateItem ??= self::resolveTemplateItem($item);

        foreach (InspectionMeasurementSlots::required(
            InspectionMeasurementSlots::fromTemplateItem($templateItem),
        ) as $slot) {
            if (! self::slotFilled($item, $slot)) {
                return false;
            }
        }

        if (in_array($status->value, InspectionTemplatePointMeta::requireObservationWhen($templateItem), true)) {
            $selected = is_array($item->selected_observations) ? $item->selected_observations : [];
            if ($selected === []) {
                return false;
            }
        }

        if (in_array($status->value, InspectionTemplatePointMeta::requireNoteWhen($templateItem), true)) {
            if (! filled($item->notes)) {
                return false;
            }
        }

        if (InspectionTemplatePointMeta::photoRequiredForCoverage($templateItem, $status)
            && $item->photos->isEmpty()) {
            return false;
        }

        return true;
    }

    public static function evidenceComplete(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): bool
    {
        return self::evidenceGaps($item, $templateItem) === [];
    }

    /**
     * Supporting documentation still missing after coverage facts are recorded.
     *
     * @return list<array{
     *     kind: string,
     *     severity: string,
     *     message: string,
     *     status: ?string,
     *     status_display: ?string
     * }>
     */
    public static function evidenceGaps(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): array
    {
        $item->loadMissing(['photos']);

        $status = self::statusFor($item);
        if (! $status instanceof InspectionChecklistStatus
            || $status === InspectionChecklistStatus::Na
            || $item->observed_state === InspectionObservedState::NotChecked
            || $item->observed_state === null) {
            return [];
        }

        $templateItem ??= self::resolveTemplateItem($item);
        $statusDisplay = InspectionTemplatePointMeta::statusDisplayLabel($templateItem, $status)
            ?? $status->label();
        $gaps = [];

        $hasPhoto = $item->photos->isNotEmpty();
        $scanRequired = (bool) ($templateItem?->requires_scan_evidence ?? false);

        if ($scanRequired) {
            $hasNote = filled($item->notes);
            if (! $hasPhoto && ! $hasNote) {
                $gaps[] = [
                    'kind' => self::GAP_SCAN,
                    'severity' => self::SEVERITY_REQUIRED,
                    'message' => 'Scan evidence missing — '.$statusDisplay.' finding recorded',
                    'status' => $status->value,
                    'status_display' => $statusDisplay,
                ];
            }

            // Scan policy accepts photo or note; do not also raise a generic photo gap.
            return $gaps;
        }

        // Supporting photo policy (when_not_green / require_photo_when / requires_photo).
        // Excluded when the same photo already counts toward coverage for this status.
        $photoForCoverage = InspectionTemplatePointMeta::photoRequiredForCoverage($templateItem, $status);
        $photoForEvidence = InspectionTemplatePointMeta::photoRequiredForStatus($templateItem, $status)
            && ! $photoForCoverage;

        if ($photoForEvidence && ! $hasPhoto) {
            $gaps[] = [
                'kind' => self::GAP_PHOTO,
                'severity' => self::SEVERITY_REQUIRED,
                'message' => 'Photo missing — '.$statusDisplay.' finding recorded',
                'status' => $status->value,
                'status_display' => $statusDisplay,
            ];
        }

        return $gaps;
    }

    /**
     * @return 'not_checked'|'missing_facts'|'addressed'|'addressed_with_evidence_gaps'
     */
    public static function coverageState(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): string
    {
        if ($item->observed_state === InspectionObservedState::NotChecked
            || $item->observed_state === null) {
            return 'not_checked';
        }

        $templateItem ??= self::resolveTemplateItem($item);

        if (! self::isCovered($item, $templateItem)) {
            return 'missing_facts';
        }

        if (self::evidenceGaps($item, $templateItem) !== []) {
            return 'addressed_with_evidence_gaps';
        }

        return 'addressed';
    }

    /**
     * @param  array{key: string, name: string, unit: ?string, required: bool, type: string}  $slot
     */
    public static function slotFilled(InspectionItem $item, array $slot): bool
    {
        $measurement = $item->measurements->first(
            fn (InspectionItemMeasurement $row): bool => strcasecmp((string) $row->name, $slot['name']) === 0
                || strcasecmp((string) $row->name, $slot['key']) === 0,
        );

        if (! $measurement instanceof InspectionItemMeasurement) {
            return false;
        }

        return filled(trim((string) $measurement->value));
    }

    /**
     * @return list<string> missing required slot names
     */
    public static function missingRequiredSlotNames(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): array
    {
        $item->loadMissing('measurements');
        $templateItem ??= self::resolveTemplateItem($item);

        $missing = [];
        foreach (InspectionMeasurementSlots::required(InspectionMeasurementSlots::fromTemplateItem($templateItem)) as $slot) {
            if (! self::slotFilled($item, $slot)) {
                $missing[] = $slot['name'];
            }
        }

        return $missing;
    }

    /**
     * Factual requirements still missing for coverage (not evidence).
     *
     * @return list<array{kind: string, message: string}>
     */
    public static function missingCoverageFacts(InspectionItem $item, ?InspectionTemplateItem $templateItem = null): array
    {
        $item->loadMissing(['measurements', 'photos']);

        if ($item->observed_state === InspectionObservedState::NotChecked
            || $item->observed_state === null) {
            return [[
                'kind' => 'condition',
                'message' => 'Condition not recorded',
            ]];
        }

        $status = self::statusFor($item);
        if ($status === InspectionChecklistStatus::Na) {
            return [];
        }

        if (! $status instanceof InspectionChecklistStatus) {
            return [[
                'kind' => 'condition',
                'message' => 'Condition not recorded',
            ]];
        }

        $templateItem ??= self::resolveTemplateItem($item);
        $missing = [];

        foreach (self::missingRequiredSlotNames($item, $templateItem) as $name) {
            $missing[] = [
                'kind' => self::GAP_MEASUREMENT,
                'message' => 'Measurement required: '.$name,
            ];
        }

        if (in_array($status->value, InspectionTemplatePointMeta::requireObservationWhen($templateItem), true)) {
            $selected = is_array($item->selected_observations) ? $item->selected_observations : [];
            if ($selected === []) {
                $missing[] = [
                    'kind' => self::GAP_OBSERVATION,
                    'message' => 'Structured observation required',
                ];
            }
        }

        if (in_array($status->value, InspectionTemplatePointMeta::requireNoteWhen($templateItem), true)
            && ! filled($item->notes)) {
            $missing[] = [
                'kind' => self::GAP_NOTE,
                'message' => 'Note required for this finding',
            ];
        }

        if (InspectionTemplatePointMeta::photoRequiredForCoverage($templateItem, $status)
            && $item->photos->isEmpty()) {
            $missing[] = [
                'kind' => self::GAP_PHOTO,
                'message' => 'Photo required to complete this procedure',
            ];
        }

        return $missing;
    }

    private static function statusFor(InspectionItem $item): ?InspectionChecklistStatus
    {
        return InspectionChecklistStatus::fromObservedState(
            $item->observed_state instanceof InspectionObservedState
                ? $item->observed_state
                : InspectionObservedState::tryFrom((string) $item->observed_state) ?? InspectionObservedState::NotChecked,
        );
    }

    private static function resolveTemplateItem(InspectionItem $item): ?InspectionTemplateItem
    {
        return $item->inspection_template_item_id !== null
            ? InspectionTemplateItem::query()->find($item->inspection_template_item_id)
            : null;
    }
}
