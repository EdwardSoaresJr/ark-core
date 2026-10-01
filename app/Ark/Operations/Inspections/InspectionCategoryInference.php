<?php

namespace App\Ark\Operations\Inspections;

final class InspectionCategoryInference
{
    public static function fromLabel(string $label): InspectionItemCategory
    {
        $haystack = strtolower($label);

        return match (true) {
            self::matches($haystack, ['brake', 'pad', 'rotor', 'caliper']) => InspectionItemCategory::Brakes,
            self::matches($haystack, ['tire', 'tread', 'wheel']) => InspectionItemCategory::Tires,
            self::matches($haystack, ['battery', 'cca']) => InspectionItemCategory::Battery,
            self::matches($haystack, ['oil', 'coolant', 'fluid', 'transmission', 'power steering']) => InspectionItemCategory::Fluids,
            self::matches($haystack, ['belt', 'serpentine']) => InspectionItemCategory::Belts,
            self::matches($haystack, ['hose']) => InspectionItemCategory::Hoses,
            self::matches($haystack, ['suspension', 'shock', 'strut', 'spring', 'bushing']) => InspectionItemCategory::Suspension,
            self::matches($haystack, ['steering', 'tie rod', 'alignment']) => InspectionItemCategory::Steering,
            self::matches($haystack, ['light', 'lamp', 'bulb', 'headlight', 'tail']) => InspectionItemCategory::Lights,
            default => InspectionItemCategory::Fluids,
        };
    }

    /**
     * @param  list<string>  $needles
     */
    private static function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
