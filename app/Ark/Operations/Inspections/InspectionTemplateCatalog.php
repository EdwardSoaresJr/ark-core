<?php

namespace App\Ark\Operations\Inspections;

final class InspectionTemplateCatalog
{
    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     enabled: bool,
     *     is_default: bool,
     *     categories: list<array{
     *         id: int,
     *         name: string,
     *         items: list<array{
     *             id: int,
     *             label: string,
     *             enabled: bool,
     *             requires_photo: bool,
     *             measurement_name: ?string,
     *             measurement_unit: ?string,
     *         }>
     *     }>
     * }>
     */
    public static function settingsFormData(): array
    {
        DefaultInspectionTemplateCatalog::seedIfMissing();

        return InspectionTemplate::query()
            ->with(['categories.items'])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (InspectionTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'enabled' => $template->enabled,
                'is_default' => $template->is_default,
                'categories' => $template->categories->map(fn (InspectionTemplateCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'items' => $category->items->map(fn (InspectionTemplateItem $item): array => [
                        'id' => $item->id,
                        'label' => $item->label,
                        'enabled' => $item->enabled,
                        'requires_photo' => $item->requires_photo,
                        'measurement_name' => $item->measurement_name,
                        'measurement_unit' => $item->measurement_unit,
                    ])->all(),
                ])->all(),
            ])
            ->all();
    }
}
