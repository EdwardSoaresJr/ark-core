<?php

namespace App\Ark\Operations\Inspections;

/**
 * Template measurement slot helpers.
 *
 * @phpstan-type Slot array{key: string, name: string, unit: ?string, required: bool, type: string}
 */
final class InspectionMeasurementSlots
{
    /**
     * @return list<Slot>
     */
    public static function fromTemplateItem(?InspectionTemplateItem $templateItem): array
    {
        if (! $templateItem instanceof InspectionTemplateItem) {
            return [];
        }

        $slots = $templateItem->measurement_slots;
        if (is_array($slots) && $slots !== []) {
            return array_values(array_map(function (array $slot): array {
                return [
                    'key' => (string) ($slot['key'] ?? ''),
                    'name' => (string) ($slot['name'] ?? $slot['key'] ?? 'Measurement'),
                    'unit' => isset($slot['unit']) ? (string) $slot['unit'] : null,
                    'required' => (bool) ($slot['required'] ?? false),
                    'type' => (string) ($slot['type'] ?? 'number'),
                ];
            }, $slots));
        }

        if (filled($templateItem->measurement_name)) {
            return [[
                'key' => 'primary',
                'name' => (string) $templateItem->measurement_name,
                'unit' => $templateItem->measurement_unit,
                'required' => true,
                'type' => 'number',
            ]];
        }

        return [];
    }

    /**
     * @param  list<Slot>  $slots
     * @return list<Slot>
     */
    public static function required(array $slots): array
    {
        return array_values(array_filter($slots, fn (array $slot): bool => $slot['required'] === true));
    }

    public static function treadThreeZone(): array
    {
        return [
            ['key' => 'outer', 'name' => 'Outer', 'unit' => '/32"', 'required' => true, 'type' => 'number'],
            ['key' => 'center', 'name' => 'Center', 'unit' => '/32"', 'required' => true, 'type' => 'number'],
            ['key' => 'inner', 'name' => 'Inner', 'unit' => '/32"', 'required' => true, 'type' => 'number'],
        ];
    }

    public static function tirePsiFourCorner(): array
    {
        return [
            ['key' => 'lf_psi', 'name' => 'LF PSI', 'unit' => 'PSI', 'required' => true, 'type' => 'number'],
            ['key' => 'rf_psi', 'name' => 'RF PSI', 'unit' => 'PSI', 'required' => true, 'type' => 'number'],
            ['key' => 'lr_psi', 'name' => 'LR PSI', 'unit' => 'PSI', 'required' => true, 'type' => 'number'],
            ['key' => 'rr_psi', 'name' => 'RR PSI', 'unit' => 'PSI', 'required' => true, 'type' => 'number'],
        ];
    }

    /**
     * Corner tire — tread SM + pressure SM at the same wheel.
     *
     * @return list<Slot>
     */
    public static function cornerTire(): array
    {
        return [
            ...self::treadThreeZone(),
            ['key' => 'psi', 'name' => 'Pressure', 'unit' => 'PSI', 'required' => true, 'type' => 'number'],
        ];
    }

    public static function discBrakePadsAndRotor(): array
    {
        // Rotor CR lives on the point's observed_state (condition buttons).
        // Pad thicknesses are SM — Good alone does not address the point.
        // Corner Inspection v1 uses Inner/Outer; keys stay compatible with comparison.
        return [
            ['key' => 'inner', 'name' => 'Inner pad', 'unit' => 'mm', 'required' => true, 'type' => 'number'],
            ['key' => 'outer', 'name' => 'Outer pad', 'unit' => 'mm', 'required' => true, 'type' => 'number'],
        ];
    }

    public static function drumBrake(): array
    {
        return [
            ['key' => 'lining', 'name' => 'Lining / shoe', 'unit' => null, 'required' => true, 'type' => 'condition'],
            ['key' => 'hardware', 'name' => 'Hardware / adjuster', 'unit' => null, 'required' => true, 'type' => 'condition'],
        ];
    }
}
