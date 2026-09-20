<?php

namespace App\Ark\Operations\Inspections;

final class DefaultInspectionTemplateCatalog
{
    private static ?InspectionTemplate $seededStandard = null;

    /**
     * @return list<array{name: string, items: list<array{label: string, requires_photo?: bool, measurement_name?: string, measurement_unit?: string}>}>
     */
    public static function generalVehicleInspection(): array
    {
        // Legacy shape retained for settings/tests that still reference GVI structure.
        return [
            [
                'name' => 'Tires',
                'items' => [
                    ['label' => 'LF tire tread', 'measurement_name' => 'Tread depth', 'measurement_unit' => '/32"'],
                    ['label' => 'RF tire tread', 'measurement_name' => 'Tread depth', 'measurement_unit' => '/32"'],
                    ['label' => 'LR tire tread', 'measurement_name' => 'Tread depth', 'measurement_unit' => '/32"'],
                    ['label' => 'RR tire tread', 'measurement_name' => 'Tread depth', 'measurement_unit' => '/32"'],
                    ['label' => 'Tire pressure'],
                    ['label' => 'Spare tire'],
                ],
            ],
            [
                'name' => 'Brakes',
                'items' => [
                    ['label' => 'Front brake pads', 'requires_photo' => true],
                    ['label' => 'Rear brake pads', 'requires_photo' => true],
                    ['label' => 'Front rotors'],
                    ['label' => 'Rear rotors'],
                    ['label' => 'Brake fluid condition'],
                ],
            ],
            [
                'name' => 'Fluids',
                'items' => [
                    ['label' => 'Engine oil'],
                    ['label' => 'Coolant'],
                    ['label' => 'Power steering fluid'],
                    ['label' => 'Transmission fluid'],
                    ['label' => 'Washer fluid'],
                ],
            ],
            [
                'name' => 'Steering / Suspension',
                'items' => [
                    ['label' => 'Steering linkage'],
                    ['label' => 'Front suspension'],
                    ['label' => 'Rear suspension'],
                    ['label' => 'Shocks / struts'],
                ],
            ],
            [
                'name' => 'Under Hood',
                'items' => [
                    ['label' => 'Battery'],
                    ['label' => 'Serpentine belt'],
                    ['label' => 'Coolant hoses'],
                    ['label' => 'Air filter'],
                ],
            ],
            [
                'name' => 'Under Vehicle',
                'items' => [
                    ['label' => 'Exhaust system'],
                    ['label' => 'CV boots / axles'],
                    ['label' => 'Fluid leaks'],
                ],
            ],
            [
                'name' => 'Lights',
                'items' => [
                    ['label' => 'Headlights'],
                    ['label' => 'Brake lights'],
                    ['label' => 'Turn signals'],
                    ['label' => 'License plate light'],
                ],
            ],
            [
                'name' => 'Road Test',
                'items' => [
                    ['label' => 'Brake performance'],
                    ['label' => 'Steering pull / drift'],
                    ['label' => 'Unusual noises'],
                    ['label' => 'Vibration'],
                ],
            ],
        ];
    }

    /**
     * Ensure Standard + PPI exist; archive legacy GVI as editable non-default.
     * Never overwrites an existing template's categories/items (shop edits survive).
     */
    public static function seedIfMissing(): InspectionTemplate
    {
        if (self::$seededStandard instanceof InspectionTemplate && self::$seededStandard->exists) {
            return self::$seededStandard;
        }

        self::archiveLegacyGeneralVehicleInspection();

        $standard = self::ensureTemplate(
            slug: InspectionTemplateSlugs::STANDARD,
            name: 'Standard Vehicle Inspection',
            isDefault: true,
            position: 0,
            definition: FrozenInspectionTemplateDefinitions::standard(),
        );

        self::ensureTemplate(
            slug: InspectionTemplateSlugs::PPI,
            name: 'Pre-Purchase Inspection',
            isDefault: false,
            position: 1,
            definition: FrozenInspectionTemplateDefinitions::prePurchase(),
        );

        self::$seededStandard = $standard->load(['categories.items']);

        return self::$seededStandard;
    }

    public static function forgetSeededCache(): void
    {
        self::$seededStandard = null;
    }

    public static function standardTemplate(): ?InspectionTemplate
    {
        $seeded = self::seedIfMissing();

        if ($seeded->slug === InspectionTemplateSlugs::STANDARD && $seeded->enabled) {
            return $seeded;
        }

        return InspectionTemplate::query()
            ->where('slug', InspectionTemplateSlugs::STANDARD)
            ->where('enabled', true)
            ->first();
    }

    public static function ppiTemplate(): ?InspectionTemplate
    {
        self::seedIfMissing();

        return InspectionTemplate::query()
            ->where('slug', InspectionTemplateSlugs::PPI)
            ->where('enabled', true)
            ->first();
    }

    /**
     * Rebuild Standard template categories/items to Corner Inspection v1.
     *
     * Does not mutate existing runtime InspectionItem rows. Open inspections keep
     * their captured points; new applies use the rebuilt template.
     */
    public static function rebuildStandardCornerInspectionV1(): InspectionTemplate
    {
        self::seedIfMissing();

        $template = InspectionTemplate::query()
            ->where('slug', InspectionTemplateSlugs::STANDARD)
            ->first();

        if (! $template instanceof InspectionTemplate) {
            return self::seedIfMissing();
        }

        if (! self::standardHasCornerV1($template)) {
            $categoryIds = $template->categories()->pluck('id');
            if ($categoryIds->isNotEmpty()) {
                InspectionTemplateItem::query()
                    ->whereIn('inspection_template_category_id', $categoryIds)
                    ->delete();
                $template->categories()->delete();
            }

            self::insertDefinitionCategories($template, FrozenInspectionTemplateDefinitions::standard());
        }

        self::syncStandardAllowsNaFromDefinition($template);
        self::disableNaOnLegacyTireBrakeCategories($template);

        return $template->fresh(['categories.items']);
    }

    /**
     * Apply Frozen allows_na flags onto existing Standard template items by point_key.
     */
    public static function syncStandardAllowsNaFromDefinition(?InspectionTemplate $template = null): void
    {
        $template ??= InspectionTemplate::query()
            ->where('slug', InspectionTemplateSlugs::STANDARD)
            ->first();

        if (! $template instanceof InspectionTemplate) {
            return;
        }

        $allowsByKey = [];
        foreach (FrozenInspectionTemplateDefinitions::standard() as $category) {
            foreach ($category['items'] as $item) {
                $key = $item['point_key'] ?? null;
                if (is_string($key) && $key !== '') {
                    $allowsByKey[$key] = (bool) ($item['allows_na'] ?? true);
                }
            }
        }

        if ($allowsByKey === []) {
            return;
        }

        $items = InspectionTemplateItem::query()
            ->whereIn('inspection_template_category_id', $template->categories()->pluck('id'))
            ->whereIn('point_key', array_keys($allowsByKey))
            ->get();

        foreach ($items as $item) {
            $allows = $allowsByKey[$item->point_key] ?? true;
            if ((bool) $item->allows_na !== $allows) {
                $item->forceFill(['allows_na' => $allows])->save();
            }
        }
    }

    /**
     * Legacy Standard categories named Tires / Brakes — every vehicle has them; no N/A.
     */
    public static function disableNaOnLegacyTireBrakeCategories(?InspectionTemplate $template = null): void
    {
        $template ??= InspectionTemplate::query()
            ->where('slug', InspectionTemplateSlugs::STANDARD)
            ->first();

        if (! $template instanceof InspectionTemplate) {
            return;
        }

        $categoryIds = $template->categories()
            ->whereIn('name', ['Tires', 'Brakes'])
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        InspectionTemplateItem::query()
            ->whereIn('inspection_template_category_id', $categoryIds)
            ->where('allows_na', true)
            ->update(['allows_na' => false]);
    }

    public static function standardHasCornerV1(?InspectionTemplate $template = null): bool
    {
        $template ??= InspectionTemplate::query()
            ->where('slug', InspectionTemplateSlugs::STANDARD)
            ->first();

        if (! $template instanceof InspectionTemplate) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('inspection_template_items', 'builder_meta')) {
            return false;
        }

        return InspectionTemplateItem::query()
            ->whereIn('inspection_template_category_id', $template->categories()->pluck('id'))
            ->where('point_key', 'std_lf_tire')
            ->whereNotNull('builder_meta')
            ->exists();
    }

    private static function archiveLegacyGeneralVehicleInspection(): void
    {
        $legacy = InspectionTemplate::query()
            ->where(function ($query): void {
                $query->where('slug', InspectionTemplateSlugs::GENERAL_LEGACY)
                    ->orWhere('name', 'General Vehicle Inspection');
            })
            ->first();

        if (! $legacy instanceof InspectionTemplate) {
            return;
        }

        $legacy->forceFill([
            'slug' => InspectionTemplateSlugs::GENERAL_LEGACY,
            'enabled' => false,
            'is_default' => false,
            'archived_at' => $legacy->archived_at ?? now(),
            'position' => 99,
        ])->save();
    }

    /**
     * @param  list<array{name: string, items: list<array<string, mixed>>}>  $definition
     */
    private static function ensureTemplate(
        string $slug,
        string $name,
        bool $isDefault,
        int $position,
        array $definition,
    ): InspectionTemplate {
        $existing = InspectionTemplate::query()->where('slug', $slug)->first();

        if ($existing instanceof InspectionTemplate) {
            if ($isDefault && ! $existing->is_default) {
                InspectionTemplate::query()->where('id', '!=', $existing->id)->update(['is_default' => false]);
                $existing->forceFill(['is_default' => true, 'enabled' => true])->save();
            }

            return $existing;
        }

        if ($isDefault) {
            InspectionTemplate::query()->update(['is_default' => false]);
        }

        $template = InspectionTemplate::query()->create([
            'name' => $name,
            'slug' => $slug,
            'enabled' => true,
            'is_default' => $isDefault,
            'position' => $position,
            'archived_at' => null,
        ]);

        self::insertDefinitionCategories($template, $definition);

        return $template;
    }

    /**
     * @param  list<array{name: string, items: list<array<string, mixed>>}>  $definition
     */
    private static function insertDefinitionCategories(InspectionTemplate $template, array $definition): void
    {
        $now = now();
        $itemRows = [];

        foreach ($definition as $categoryIndex => $category) {
            $categoryModel = $template->categories()->create([
                'name' => $category['name'],
                'position' => $categoryIndex + 1,
            ]);

            foreach ($category['items'] as $itemIndex => $item) {
                $itemRows[] = [
                    'inspection_template_category_id' => $categoryModel->id,
                    'label' => $item['label'],
                    'point_key' => $item['point_key'] ?? null,
                    'position' => $itemIndex + 1,
                    'requires_photo' => (bool) ($item['requires_photo'] ?? false),
                    'requires_scan_evidence' => (bool) ($item['requires_scan_evidence'] ?? false),
                    'measurement_name' => $item['measurement_name'] ?? null,
                    'measurement_unit' => $item['measurement_unit'] ?? null,
                    'measurement_slots' => isset($item['measurement_slots'])
                        ? json_encode($item['measurement_slots'], JSON_THROW_ON_ERROR)
                        : null,
                    'builder_meta' => isset($item['builder_meta'])
                        ? json_encode($item['builder_meta'], JSON_THROW_ON_ERROR)
                        : null,
                    'gate_group' => $item['gate_group'] ?? null,
                    'axle_role' => $item['axle_role'] ?? null,
                    'allows_na' => (bool) ($item['allows_na'] ?? true),
                    'enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($itemRows !== []) {
            InspectionTemplateItem::query()->insert($itemRows);
        }
    }
}
