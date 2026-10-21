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

                    $changes = [
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
                    ];

                    $slots = $this->slots($item, $itemData['slots'] ?? null);
                    if ($slots !== null) {
                        $changes['measurement_slots'] = $slots;
                    }

                    $item->update($changes);
                }
            }
        }
    }

    /**
     * Keep each slot's key and required flag. Name, type, and unit come from the form.
     *
     * @param  mixed  $posted
     * @return list<array{key: string, name: string, unit: ?string, required: bool, type: string}>|null
     */
    private function slots(InspectionTemplateItem $item, mixed $posted): ?array
    {
        if (! is_array($posted)) {
            return null;
        }

        $existing = InspectionMeasurementSlots::fromTemplateItem($item);
        if ($existing === []) {
            return null;
        }

        $updated = [];
        foreach ($existing as $index => $slot) {
            $row = $posted[$index] ?? $posted[$slot['key']] ?? null;
            if (! is_array($row)) {
                $updated[] = $slot;

                continue;
            }

            $type = (string) ($row['type'] ?? '');
            if (! in_array($type, ['number', 'text', 'condition'], true)) {
                $type = $slot['type'];
            }

            $updated[] = [
                'key' => $slot['key'],
                'name' => filled($row['name'] ?? null) ? trim((string) $row['name']) : $slot['name'],
                'unit' => array_key_exists('unit', $row)
                    ? (filled($row['unit']) ? trim((string) $row['unit']) : null)
                    : $slot['unit'],
                'required' => $slot['required'],
                'type' => $type,
            ];
        }

        return $updated;
    }
}
