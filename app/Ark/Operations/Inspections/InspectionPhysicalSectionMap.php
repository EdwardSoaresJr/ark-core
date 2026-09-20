<?php

namespace App\Ark\Operations\Inspections;

/**
 * Physical workflow grouping for the technician section walk.
 *
 * Projection only — does not rewrite template categories or InspectionItem identity.
 * Corner Inspection v1 is the first Standard stage (LF → LR → RR → RF).
 *
 * Placement authority (runtime):
 * - InspectionItem.walk_section — snapshotted from Builder at apply (Corner / new)
 * - checklist_category_name — legacy when walk_section is null
 *
 * Walk never reads live Builder definitions for placement.
 * Unknown categories (e.g. PPI) fall through as one section per category name.
 */
final class InspectionPhysicalSectionMap
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     optional: bool,
     *     sections: list<array{key: string, label: string, categories: list<string>}>
     * }>
     */
    public static function standardStages(): array
    {
        return [
            [
                'key' => 'corner_inspection',
                'label' => 'Corner Inspection',
                'optional' => false,
                'sections' => [
                    [
                        'key' => 'rear_axle',
                        'label' => 'Rear axle',
                        'categories' => ['Rear axle'],
                    ],
                    [
                        'key' => 'left_front',
                        'label' => 'Left Front',
                        'categories' => ['Left Front'],
                    ],
                    [
                        'key' => 'left_rear',
                        'label' => 'Left Rear',
                        'categories' => ['Left Rear'],
                    ],
                    [
                        'key' => 'right_rear',
                        'label' => 'Right Rear',
                        'categories' => ['Right Rear'],
                    ],
                    [
                        'key' => 'right_front',
                        'label' => 'Right Front',
                        'categories' => ['Right Front'],
                    ],
                    [
                        'key' => 'brake_system',
                        'label' => 'Brake Fluid & Parking Brake',
                        'categories' => ['Brake system'],
                    ],
                ],
            ],
            [
                'key' => 'on_the_lift',
                'label' => 'On the lift',
                'optional' => false,
                'sections' => [
                    [
                        'key' => 'under_vehicle',
                        'label' => 'Under Vehicle',
                        'categories' => ['Under vehicle'],
                    ],
                ],
            ],
            [
                'key' => 'on_the_ground',
                'label' => 'Back on the ground',
                'optional' => false,
                'sections' => [
                    [
                        'key' => 'under_hood',
                        'label' => 'Under Hood & Electrical',
                        'categories' => ['Under hood'],
                    ],
                    [
                        'key' => 'exterior_safety',
                        'label' => 'Exterior & Safety',
                        'categories' => ['Arrival / outside'],
                    ],
                ],
            ],
            [
                'key' => 'road_test',
                'label' => 'Road test',
                'optional' => true,
                'sections' => [
                    [
                        'key' => 'road_operational',
                        'label' => 'Road / Operational',
                        'categories' => ['Road / operational'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Resolve walk placement from runtime item authority.
     *
     * @return array{stage_key: string, stage_label: string, section_key: string, section_label: string, optional: bool}|null
     */
    public static function placementForItem(InspectionItem $item): ?array
    {
        $fromSnapshot = self::placementForSectionKey(
            is_string($item->walk_section) && $item->walk_section !== ''
                ? $item->walk_section
                : null,
        );
        if ($fromSnapshot !== null) {
            return $fromSnapshot;
        }

        $category = (string) ($item->checklist_category_name ?? $item->categoryLabel());

        return self::placementForCategory($category);
    }

    /**
     * Resolve section key for a checklist category name under Standard mapping.
     */
    public static function sectionKeyForCategory(string $categoryName): ?string
    {
        return self::placementForCategory($categoryName)['section_key'] ?? null;
    }

    /**
     * @return array{stage_key: string, stage_label: string, section_key: string, section_label: string, optional: bool}|null
     */
    public static function placementForCategory(string $categoryName): ?array
    {
        $normalized = mb_strtolower(trim($categoryName));

        foreach (self::standardStages() as $stage) {
            foreach ($stage['sections'] as $section) {
                foreach ($section['categories'] as $category) {
                    if (mb_strtolower($category) === $normalized) {
                        return [
                            'stage_key' => $stage['key'],
                            'stage_label' => $stage['label'],
                            'section_key' => $section['key'],
                            'section_label' => $section['label'],
                            'optional' => $stage['optional'],
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array{stage_key: string, stage_label: string, section_key: string, section_label: string, optional: bool}|null
     */
    public static function placementForSectionKey(?string $sectionKey): ?array
    {
        if ($sectionKey === null || $sectionKey === '') {
            return null;
        }

        foreach (self::standardStages() as $stage) {
            foreach ($stage['sections'] as $section) {
                if ($section['key'] === $sectionKey) {
                    return [
                        'stage_key' => $stage['key'],
                        'stage_label' => $stage['label'],
                        'section_key' => $section['key'],
                        'section_label' => $section['label'],
                        'optional' => $stage['optional'],
                    ];
                }
            }
        }

        return null;
    }
}
