<?php

namespace App\Ark\Tech;

use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionChecklistItems;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemLivingRecordProjection;
use App\Ark\Operations\Inspections\InspectionPhysicalSectionMap;
use App\Ark\Operations\Inspections\InspectionWalkVisibility;
use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Technician DVI tasks: living records plus interaction kind derived from slots/meta.
 * No shop, template, or item-id hardcoding.
 */
final class TechDviTaskProjector
{
    public function __construct(
        private readonly InspectionItemLivingRecordProjection $livingRecord,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(RepairOrder $repairOrder, Inspection $inspection): array
    {
        $ordered = InspectionWalkVisibility::visibleItems(
            $inspection,
            InspectionChecklistItems::orderedChecklistItems($inspection),
        );
        $tasks = [];

        foreach ($ordered as $item) {
            $record = InspectionChecklistItems::livingRecordForItem(
                $repairOrder,
                $inspection,
                $item,
                $this->livingRecord,
                'mobile',
            );
            $tasks[] = $this->taskFromLiving($record, $item);
        }

        $sections = [];
        foreach ($tasks as $task) {
            $name = (string) $task['section_name'];
            $sections[$name] ??= [
                'name' => $name,
                'walk_section' => $task['walk_section'],
                'items' => [],
            ];
            $sections[$name]['items'][] = $task;
        }

        $sectionList = [];
        foreach (array_values($sections) as $section) {
            $count = count($section['items']);
            foreach ($section['items'] as $index => $task) {
                $progress = [
                    'index' => $index + 1,
                    'total' => $count,
                    'label' => $section['name'].' · '.($index + 1).' of '.$count,
                ];
                $section['items'][$index]['progress'] = $progress;
                foreach ($tasks as $taskIndex => $flat) {
                    if ((int) ($flat['id'] ?? 0) === (int) ($task['id'] ?? 0)) {
                        $tasks[$taskIndex]['progress'] = $progress;
                    }
                }
            }
            $sectionList[] = $section;
        }

        $brakeItems = $tasks;

        return [
            'sections' => $sectionList,
            'tasks' => $tasks,
            'brake_items' => $brakeItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $living
     * @return array<string, mixed>
     */
    private function taskFromLiving(array $living, InspectionItem $item): array
    {
        $slots = [];
        foreach ($living['measurement_slots'] ?? [] as $slot) {
            if (($slot['type'] ?? 'number') !== 'number') {
                continue;
            }
            $key = (string) ($slot['key'] ?? '');
            $slots[] = [
                'key' => $key,
                'name' => (string) ($slot['name'] ?? $key),
                'unit' => $slot['unit'] ?? null,
                'required' => (bool) ($slot['required'] ?? false),
                'value' => (string) ($slot['value'] ?? ''),
                'display_label' => $this->displayLabel((string) ($slot['name'] ?? $key), $key),
                'aliases' => TechSchemaSpeechParser::aliasesForSlot($key, (string) ($slot['name'] ?? '')),
            ];
        }

        $kind = 'finding';
        if (($living['is_axle_gate'] ?? false) === true) {
            $kind = 'selection';
        } elseif (count($slots) >= 2) {
            $kind = 'positioned_measurement';
        } elseif (count($slots) === 1) {
            $kind = 'measurement';
        } elseif (($living['condition_options'] ?? []) !== []) {
            $kind = 'condition';
        }

        $conditions = $living['condition_options'] ?? [];
        $photoRequired = (bool) ($living['requires_photo'] ?? false);

        return [
            ...$living,
            'section_name' => $this->sectionName($item, $living),
            'walk_section' => $item->walk_section,
            'interaction' => [
                'kind' => $kind,
                'condition_enabled' => $conditions !== [] && $kind !== 'selection',
                'finding_enabled' => $kind !== 'selection',
                'photo_required' => $photoRequired,
                'photo_optional' => ! $photoRequired,
                'slots' => $slots,
                'selection_options' => $kind === 'selection'
                    ? [
                        ['value' => 'disc', 'label' => 'Disc'],
                        ['value' => 'drum', 'label' => 'Drum'],
                    ]
                    : [],
                'selection_value' => $living['rear_axle_brake_type'] ?? null,
            ],
            'voice_schema' => [
                'item_label' => $living['label'] ?? '',
                'fields' => array_values(array_filter([
                    ...array_map(fn (array $slot): array => [
                        'key' => $slot['key'],
                        'type' => 'number',
                        'unit' => $slot['unit'],
                        'aliases' => $slot['aliases'],
                    ], $slots),
                    $kind !== 'selection' ? ['key' => 'finding', 'type' => 'text'] : null,
                    ($conditions !== [] && $kind !== 'selection') ? [
                        'key' => 'condition',
                        'type' => 'enum',
                        'options' => array_column($conditions, 'value'),
                    ] : null,
                ])),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $living
     */
    private function sectionName(InspectionItem $item, array $living): string
    {
        $placement = InspectionPhysicalSectionMap::placementForItem($item);

        if (is_array($placement) && filled($placement['section_label'] ?? null)) {
            return (string) $placement['section_label'];
        }

        return (string) ($living['category_name'] ?? 'Inspection');
    }

    private function displayLabel(string $name, string $key): string
    {
        $compact = strtolower(str_replace(['_', '-', ' '], '', $key.$name));
        if (str_contains($compact, 'left') || in_array(strtolower($key), ['l', 'lf', 'lr'], true)) {
            return 'LEFT';
        }
        if (str_contains($compact, 'right') || in_array(strtolower($key), ['r', 'rf', 'rr'], true)) {
            return 'RIGHT';
        }

        return strtoupper($name);
    }
}
