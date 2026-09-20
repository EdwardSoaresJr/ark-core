<?php

namespace App\Ark\Operations\Inspections;

/**
 * Reads Builder metadata from inspection_template_items.builder_meta.
 *
 * @phpstan-type BuilderMeta array{
 *     condition_palette?: string,
 *     photo_policy?: string,
 *     observation_library?: string,
 *     expand_when?: list<string>,
 *     require_observation_when?: list<string>,
 *     require_note_when?: list<string>,
 *     require_photo_when?: list<string>,
 *     corner?: string,
 *     group?: string,
 *     walk_section?: string
 * }
 */
final class InspectionTemplatePointMeta
{
    public const PALETTE_GYR = 'gyr';

    public const PHOTO_WHEN_NOT_GREEN = 'when_not_green';

    /**
     * @return BuilderMeta
     */
    public static function from(?InspectionTemplateItem $templateItem): array
    {
        if (! $templateItem instanceof InspectionTemplateItem) {
            return [];
        }

        $meta = $templateItem->builder_meta;

        return is_array($meta) ? $meta : [];
    }

    public static function conditionPalette(?InspectionTemplateItem $templateItem): ?string
    {
        $palette = self::from($templateItem)['condition_palette'] ?? null;

        return is_string($palette) && $palette !== '' ? $palette : null;
    }

    /**
     * @return list<array{value: string, label: string, display: string}>
     */
    public static function conditionOptions(?InspectionTemplateItem $templateItem, bool $allowsNa = true): array
    {
        if (self::conditionPalette($templateItem) === self::PALETTE_GYR) {
            $options = [
                [
                    'value' => InspectionChecklistStatus::Good->value,
                    'label' => 'Green',
                    'display' => 'Green',
                ],
                [
                    'value' => InspectionChecklistStatus::Monitor->value,
                    'label' => 'Yellow',
                    'display' => 'Yellow',
                ],
                [
                    'value' => InspectionChecklistStatus::Failed->value,
                    'label' => 'Red',
                    'display' => 'Red',
                ],
            ];

            if ($allowsNa && ($templateItem?->allows_na ?? true)) {
                $options[] = [
                    'value' => InspectionChecklistStatus::Na->value,
                    'label' => 'N/A',
                    'display' => 'N/A',
                ];
            }

            return $options;
        }

        return collect(InspectionChecklistStatus::ordered())
            ->filter(function (InspectionChecklistStatus $status) use ($templateItem, $allowsNa): bool {
                if ($status === InspectionChecklistStatus::Na) {
                    return $allowsNa && ($templateItem?->allows_na ?? true);
                }

                return true;
            })
            ->map(fn (InspectionChecklistStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'display' => $status->label(),
            ])
            ->values()
            ->all();
    }

    public static function statusDisplayLabel(
        ?InspectionTemplateItem $templateItem,
        ?InspectionChecklistStatus $status,
    ): ?string {
        if (! $status instanceof InspectionChecklistStatus) {
            return null;
        }

        foreach (self::conditionOptions($templateItem) as $option) {
            if (($option['value'] ?? '') === $status->value) {
                return (string) $option['display'];
            }
        }

        return $status->label();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function observationOptions(?InspectionTemplateItem $templateItem): array
    {
        $library = self::from($templateItem)['observation_library'] ?? null;

        return InspectionObservationLibraries::resolve(is_string($library) ? $library : null);
    }

    /**
     * @return list<string> checklist status values that expand the detail panel
     */
    public static function expandWhen(?InspectionTemplateItem $templateItem): array
    {
        $meta = self::from($templateItem);
        if (isset($meta['expand_when']) && is_array($meta['expand_when'])) {
            return array_values(array_map('strval', $meta['expand_when']));
        }

        if (self::conditionPalette($templateItem) === self::PALETTE_GYR) {
            return [
                InspectionChecklistStatus::Monitor->value,
                InspectionChecklistStatus::Failed->value,
            ];
        }

        return [
            InspectionChecklistStatus::Monitor->value,
            InspectionChecklistStatus::NeedsAttention->value,
            InspectionChecklistStatus::Failed->value,
        ];
    }

    public static function shouldExpandForStatus(
        ?InspectionTemplateItem $templateItem,
        ?InspectionChecklistStatus $status,
    ): bool {
        if (! $status instanceof InspectionChecklistStatus) {
            return false;
        }

        return in_array($status->value, self::expandWhen($templateItem), true);
    }

    /**
     * @return list<string>
     */
    public static function requirePhotoWhen(?InspectionTemplateItem $templateItem): array
    {
        $meta = self::from($templateItem);
        if (isset($meta['require_photo_when']) && is_array($meta['require_photo_when'])) {
            return array_values(array_map('strval', $meta['require_photo_when']));
        }

        $policy = $meta['photo_policy'] ?? null;
        if ($policy === self::PHOTO_WHEN_NOT_GREEN
            || (bool) ($templateItem?->requires_photo ?? false)
            || (bool) ($templateItem?->requires_scan_evidence ?? false)) {
            if ($policy === self::PHOTO_WHEN_NOT_GREEN || self::conditionPalette($templateItem) === self::PALETTE_GYR) {
                return [
                    InspectionChecklistStatus::Monitor->value,
                    InspectionChecklistStatus::Failed->value,
                ];
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public static function requireObservationWhen(?InspectionTemplateItem $templateItem): array
    {
        $meta = self::from($templateItem);
        if (isset($meta['require_observation_when']) && is_array($meta['require_observation_when'])) {
            return array_values(array_map('strval', $meta['require_observation_when']));
        }

        if (self::observationOptions($templateItem) === []) {
            return [];
        }

        return self::expandWhen($templateItem);
    }

    /**
     * @return list<string>
     */
    public static function requireNoteWhen(?InspectionTemplateItem $templateItem): array
    {
        $meta = self::from($templateItem);
        if (isset($meta['require_note_when']) && is_array($meta['require_note_when'])) {
            return array_values(array_map('strval', $meta['require_note_when']));
        }

        return self::expandWhen($templateItem);
    }

    public static function photoRequiredForStatus(
        ?InspectionTemplateItem $templateItem,
        InspectionChecklistStatus $status,
    ): bool {
        if ((bool) ($templateItem?->requires_scan_evidence ?? false)) {
            return true;
        }

        return in_array($status->value, self::requirePhotoWhen($templateItem), true)
            || ((bool) ($templateItem?->requires_photo ?? false) && $status->requiresEvidencePrompt());
    }

    /**
     * Technician walk section key from Builder metadata — null when absent (legacy).
     *
     * Prefer explicit walk_section; otherwise derive from Corner v1 corner/group.
     */
    public static function walkSection(?InspectionTemplateItem $templateItem): ?string
    {
        $meta = self::from($templateItem);
        $explicit = $meta['walk_section'] ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $corner = $meta['corner'] ?? null;
        if (! is_string($corner) || $corner === '') {
            return null;
        }

        return match ($corner) {
            'lf' => 'left_front',
            'lr' => 'left_rear',
            'rr' => 'right_rear',
            'rf' => 'right_front',
            'shared' => 'brake_system',
            default => null,
        };
    }

    /**
     * Default Demo Auto Repair Corner Builder meta — shop-configurable, not platform law.
     *
     * @return BuilderMeta
     */
    public static function shopCornerMeta(string $library, string $corner, string $group): array
    {
        return [
            'condition_palette' => self::PALETTE_GYR,
            'photo_policy' => self::PHOTO_WHEN_NOT_GREEN,
            'observation_library' => $library,
            'expand_when' => [
                InspectionChecklistStatus::Monitor->value,
                InspectionChecklistStatus::Failed->value,
            ],
            'require_observation_when' => [
                InspectionChecklistStatus::Monitor->value,
                InspectionChecklistStatus::Failed->value,
            ],
            'require_note_when' => [
                InspectionChecklistStatus::Monitor->value,
                InspectionChecklistStatus::Failed->value,
            ],
            'require_photo_when' => [
                InspectionChecklistStatus::Monitor->value,
                InspectionChecklistStatus::Failed->value,
            ],
            'corner' => $corner,
            'group' => $group,
            'walk_section' => self::walkSectionForCorner($corner),
        ];
    }

    /**
     * Measurement / CR points that use GYR without an observation library.
     *
     * @return BuilderMeta
     */
    public static function shopCornerMeasureMeta(string $corner, string $group): array
    {
        return [
            'condition_palette' => self::PALETTE_GYR,
            'photo_policy' => self::PHOTO_WHEN_NOT_GREEN,
            'expand_when' => [
                InspectionChecklistStatus::Monitor->value,
                InspectionChecklistStatus::Failed->value,
            ],
            'require_note_when' => [
                InspectionChecklistStatus::Monitor->value,
                InspectionChecklistStatus::Failed->value,
            ],
            'require_photo_when' => [
                InspectionChecklistStatus::Monitor->value,
                InspectionChecklistStatus::Failed->value,
            ],
            'corner' => $corner,
            'group' => $group,
            'walk_section' => self::walkSectionForCorner($corner),
        ];
    }

    /**
     * Rear-axle gate — Corner walk placement without a vehicle corner.
     *
     * @return BuilderMeta
     */
    public static function shopRearAxleMeta(): array
    {
        return [
            'group' => 'axle_gate',
            'walk_section' => 'rear_axle',
        ];
    }

    private static function walkSectionForCorner(string $corner): string
    {
        return match ($corner) {
            'lf' => 'left_front',
            'lr' => 'left_rear',
            'rr' => 'right_rear',
            'rf' => 'right_front',
            'shared' => 'brake_system',
            default => $corner,
        };
    }
}
