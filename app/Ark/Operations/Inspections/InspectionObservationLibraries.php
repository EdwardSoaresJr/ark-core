<?php

namespace App\Ark\Operations\Inspections;

/**
 * Platform observation chip catalogs for Builder metadata.
 * Keys are stable; labels are technician-facing.
 *
 * @phpstan-type ObservationOption array{key: string, label: string}
 */
final class InspectionObservationLibraries
{
    /**
     * @return list<ObservationOption>
     */
    public static function tire(): array
    {
        return [
            ['key' => 'uneven_wear', 'label' => 'Uneven wear'],
            ['key' => 'inside_edge_wear', 'label' => 'Inside edge wear'],
            ['key' => 'outside_edge_wear', 'label' => 'Outside edge wear'],
            ['key' => 'feathering', 'label' => 'Feathering'],
            ['key' => 'cupping', 'label' => 'Cupping'],
            ['key' => 'dry_rot', 'label' => 'Dry rot / weather checking'],
            ['key' => 'nail_puncture', 'label' => 'Nail / puncture'],
            ['key' => 'sidewall_bubble', 'label' => 'Sidewall bubble'],
            ['key' => 'cord_showing', 'label' => 'Cord showing'],
            ['key' => 'bald_tire', 'label' => 'Bald tire'],
            ['key' => 'other_tire_damage', 'label' => 'Other visible tire damage'],
        ];
    }

    /**
     * @return list<ObservationOption>
     */
    public static function wheel(): array
    {
        return [
            ['key' => 'bent_wheel', 'label' => 'Bent wheel'],
            ['key' => 'cracked_wheel', 'label' => 'Cracked wheel'],
            ['key' => 'damaged_wheel', 'label' => 'Damaged wheel'],
            ['key' => 'missing_lug_nut', 'label' => 'Missing lug nut'],
            ['key' => 'damaged_wheel_stud', 'label' => 'Damaged wheel stud'],
            ['key' => 'other_wheel', 'label' => 'Other'],
        ];
    }

    /**
     * @return list<ObservationOption>
     */
    public static function rotor(): array
    {
        return [
            ['key' => 'rust_lip', 'label' => 'Rust lip'],
            ['key' => 'heat_spotting', 'label' => 'Heat spotting'],
            ['key' => 'grooved', 'label' => 'Grooved'],
            ['key' => 'scoring', 'label' => 'Scoring'],
            ['key' => 'cracks', 'label' => 'Cracks'],
            ['key' => 'corrosion', 'label' => 'Corrosion'],
            ['key' => 'other_rotor', 'label' => 'Other'],
        ];
    }

    /**
     * @return list<ObservationOption>
     */
    public static function caliper(): array
    {
        return [
            ['key' => 'uneven_pad_wear', 'label' => 'Uneven pad wear'],
            ['key' => 'fluid_leakage', 'label' => 'Fluid leakage'],
            ['key' => 'torn_boot', 'label' => 'Torn boot'],
            ['key' => 'brake_drag_suspected', 'label' => 'Brake drag suspected'],
            ['key' => 'corrosion', 'label' => 'Corrosion'],
            ['key' => 'other_caliper', 'label' => 'Other observation'],
        ];
    }

    /**
     * @return list<ObservationOption>
     */
    public static function brakeHose(): array
    {
        return [
            ['key' => 'cracked', 'label' => 'Cracked'],
            ['key' => 'swollen', 'label' => 'Swollen'],
            ['key' => 'wet', 'label' => 'Wet'],
            ['key' => 'chafing', 'label' => 'Chafing'],
            ['key' => 'twisted', 'label' => 'Twisted'],
            ['key' => 'other_hose', 'label' => 'Other'],
        ];
    }

    /**
     * @return list<ObservationOption>
     */
    public static function resolve(?string $libraryKey): array
    {
        return match ($libraryKey) {
            'tire' => self::tire(),
            'wheel' => self::wheel(),
            'rotor' => self::rotor(),
            'caliper' => self::caliper(),
            'brake_hose' => self::brakeHose(),
            default => [],
        };
    }
}
