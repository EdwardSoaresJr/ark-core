<?php

namespace App\Ark\Operations\Inspections;

/**
 * Seeds default inspection items — organization only, not authoritative templates.
 */
final class InspectionCategoryCatalog
{
    /**
     * @return list<array{category: InspectionItemCategory, label: string}>
     */
    public static function defaultItems(): array
    {
        return [
            ['category' => InspectionItemCategory::Brakes, 'label' => 'Front brake pads'],
            ['category' => InspectionItemCategory::Brakes, 'label' => 'Rear brake pads'],
            ['category' => InspectionItemCategory::Tires, 'label' => 'Front tires'],
            ['category' => InspectionItemCategory::Tires, 'label' => 'Rear tires'],
            ['category' => InspectionItemCategory::Battery, 'label' => 'Battery'],
            ['category' => InspectionItemCategory::Fluids, 'label' => 'Engine oil'],
            ['category' => InspectionItemCategory::Fluids, 'label' => 'Coolant'],
            ['category' => InspectionItemCategory::Fluids, 'label' => 'Brake fluid'],
            ['category' => InspectionItemCategory::Belts, 'label' => 'Serpentine belt'],
            ['category' => InspectionItemCategory::Hoses, 'label' => 'Coolant hoses'],
            ['category' => InspectionItemCategory::Suspension, 'label' => 'Front suspension'],
            ['category' => InspectionItemCategory::Suspension, 'label' => 'Rear suspension'],
            ['category' => InspectionItemCategory::Steering, 'label' => 'Steering linkage'],
            ['category' => InspectionItemCategory::Lights, 'label' => 'Exterior lights'],
        ];
    }
}
