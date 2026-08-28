<?php

namespace App\Ark\Operations\Inspections;

final class InspectionTemplateSettingsUpdater
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(array $data): void
    {
        foreach ($data['templates'] ?? [] as $templateId => $templateData) {
            $template = InspectionTemplate::query()->find((int) $templateId);

            if (! $template instanceof InspectionTemplate) {
                continue;
            }

            if (array_key_exists('enabled', $templateData)) {
                $template->update([
                    'enabled' => filter_var($templateData['enabled'], FILTER_VALIDATE_BOOL),
                ]);
            }

            foreach ($templateData['categories'] ?? [] as $categoryId => $categoryData) {
                $category = InspectionTemplateCategory::query()
                    ->where('inspection_template_id', $template->id)
                    ->find((int) $categoryId);

                if (! $category instanceof InspectionTemplateCategory) {
                    continue;
                }

                if (filled($categoryData['name'] ?? null)) {
                    $category->update(['name' => trim((string) $categoryData['name'])]);
                }

                foreach ($categoryData['items'] ?? [] as $itemId => $itemData) {
                    $item = InspectionTemplateItem::query()
                        ->where('inspection_template_category_id', $category->id)
                        ->find((int) $itemId);

                    if (! $item instanceof InspectionTemplateItem) {
                        continue;
                    }

                    $item->update([
                        'label' => filled($itemData['label'] ?? null)
                            ? trim((string) $itemData['label'])
                            : $item->label,
                        'enabled' => array_key_exists('enabled', $itemData)
                            ? filter_var($itemData['enabled'], FILTER_VALIDATE_BOOL)
                            : $item->enabled,
                        'requires_photo' => array_key_exists('requires_photo', $itemData)
                            ? filter_var($itemData['requires_photo'], FILTER_VALIDATE_BOOL)
                            : $item->requires_photo,
                        'measurement_name' => array_key_exists('measurement_name', $itemData)
                            ? (filled($itemData['measurement_name']) ? trim((string) $itemData['measurement_name']) : null)
                            : $item->measurement_name,
                        'measurement_unit' => array_key_exists('measurement_unit', $itemData)
                            ? (filled($itemData['measurement_unit']) ? trim((string) $itemData['measurement_unit']) : null)
                            : $item->measurement_unit,
                    ]);
                }
            }
        }
    }
}
