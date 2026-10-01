<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class InspectionAdoptionReport
{
    /**
     * @return array{
     *     generated_at: string,
     *     activity_window: ?array{since: string, until: string},
     *     open_repair_orders: array{total: int, with_inspection: int, without_inspection: int, engaged: int},
     *     averages: array{inspections: int, recorded_items: float, measurements: float, photos: float},
     *     categories: array{most_used: list<array{category: string, recorded_items: int}>, least_used: list<array{category: string, recorded_items: int}>},
     *     friction_signals: array{notes_only_items: int, items_with_measurements: int, items_with_photos: int, state_only_items: int},
     * }
     */
    public function snapshot(?Carbon $activitySince = null, ?Carbon $activityUntil = null): array
    {
        $openRepairOrderIds = RepairOrder::query()
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->pluck('id');

        $openTotal = $openRepairOrderIds->count();

        $inspectionIds = Inspection::query()
            ->whereIn('repair_order_id', $openRepairOrderIds)
            ->pluck('id');

        $withInspection = $inspectionIds->count();
        $withoutInspection = max(0, $openTotal - $withInspection);

        $recordedItems = $this->recordedItemsQuery($inspectionIds, $activitySince, $activityUntil)->get();

        $measurementCount = $this->measurementCount($recordedItems, $activitySince, $activityUntil);
        $photoCount = $this->photoCount($recordedItems, $activitySince, $activityUntil);

        $engagedInspectionIds = $recordedItems
            ->pluck('inspection_id')
            ->unique()
            ->values();

        $inspectionDenominator = max(1, $withInspection);

        $categoryCounts = $this->categoryCounts($recordedItems);
        $orderedCategories = collect(InspectionItemCategory::ordered())
            ->map(fn (InspectionItemCategory $category): array => [
                'category' => $category->label(),
                'recorded_items' => (int) ($categoryCounts[$category->value] ?? 0),
            ])
            ->values();

        $mostUsed = $orderedCategories
            ->sortByDesc('recorded_items')
            ->values()
            ->all();

        $leastUsed = $orderedCategories
            ->sortBy('recorded_items')
            ->values()
            ->all();

        return [
            'generated_at' => now()->toDateTimeString(),
            'activity_window' => $activitySince !== null
                ? [
                    'since' => $activitySince->toDateTimeString(),
                    'until' => ($activityUntil ?? now())->toDateTimeString(),
                ]
                : null,
            'open_repair_orders' => [
                'total' => $openTotal,
                'with_inspection' => $withInspection,
                'without_inspection' => $withoutInspection,
                'engaged' => $engagedInspectionIds->count(),
            ],
            'averages' => [
                'inspections' => $withInspection,
                'recorded_items' => round($recordedItems->count() / $inspectionDenominator, 1),
                'measurements' => round($measurementCount / $inspectionDenominator, 1),
                'photos' => round($photoCount / $inspectionDenominator, 1),
            ],
            'categories' => [
                'most_used' => $mostUsed,
                'least_used' => $leastUsed,
            ],
            'friction_signals' => $this->frictionSignals($recordedItems, $activitySince, $activityUntil),
        ];
    }

    public function toMarkdown(array $data): string
    {
        $lines = [
            '# Inspection adoption audit',
            '',
            'Generated: '.$data['generated_at'],
        ];

        if ($data['activity_window'] !== null) {
            $lines[] = 'Activity window: '.$data['activity_window']['since'].' → '.$data['activity_window']['until'];
        } else {
            $lines[] = 'Activity window: all recorded facts on open repair orders';
        }

        $open = $data['open_repair_orders'];

        $lines[] = '';
        $lines[] = '## Open repair orders';
        $lines[] = '- Total open: '.$open['total'];
        $lines[] = '- With inspection workspace opened: '.$open['with_inspection'];
        $lines[] = '- Without inspection: '.$open['without_inspection'];
        $lines[] = '- Engaged (at least one recorded item): '.$open['engaged'];

        $avg = $data['averages'];

        $lines[] = '';
        $lines[] = '## Averages per opened inspection';
        $lines[] = '- Inspections opened on open ROs: '.$avg['inspections'];
        $lines[] = '- Recorded items: '.$avg['recorded_items'];
        $lines[] = '- Measurements: '.$avg['measurements'];
        $lines[] = '- Photos: '.$avg['photos'];

        $lines[] = '';
        $lines[] = '## Categories (recorded items)';
        $lines[] = '';
        $lines[] = '| Category | Recorded items |';
        $lines[] = '| --- | ---: |';

        foreach ($data['categories']['most_used'] as $row) {
            $lines[] = '| '.$row['category'].' | '.$row['recorded_items'].' |';
        }

        $signals = $data['friction_signals'];

        $lines[] = '';
        $lines[] = '## Friction signals';
        $lines[] = '- Notes only (no measurement or photo): '.$signals['notes_only_items'];
        $lines[] = '- Items with measurements: '.$signals['items_with_measurements'];
        $lines[] = '- Items with photos: '.$signals['items_with_photos'];
        $lines[] = '- State only (no notes, measurement, or photo): '.$signals['state_only_items'];
        $lines[] = '';
        $lines[] = '_Internal staff report only. Not a customer-facing dashboard._';

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, int>  $inspectionIds
     */
    private function recordedItemsQuery(Collection $inspectionIds, ?Carbon $activitySince, ?Carbon $activityUntil): Builder
    {
        return InspectionItem::query()
            ->whereIn('inspection_id', $inspectionIds)
            ->where(function (Builder $query): void {
                $query->where('observed_state', '!=', InspectionObservedState::NotChecked->value)
                    ->orWhere(function (Builder $notes): void {
                        $notes->whereNotNull('notes')->where('notes', '!=', '');
                    })
                    ->orWhereHas('measurements')
                    ->orWhereHas('photos');
            })
            ->when($activitySince !== null, function (Builder $query) use ($activitySince, $activityUntil): void {
                $until = $activityUntil ?? now();

                $query->where(function (Builder $scoped) use ($activitySince, $until): void {
                    $scoped->whereBetween('updated_at', [$activitySince, $until])
                        ->orWhereHas('measurements', fn (Builder $measurements) => $measurements->whereBetween('created_at', [$activitySince, $until]))
                        ->orWhereHas('photos', fn (Builder $photos) => $photos->whereBetween('created_at', [$activitySince, $until]));
                });
            })
            ->withCount(['measurements', 'photos']);
    }

    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     */
    private function measurementCount(Collection $recordedItems, ?Carbon $activitySince, ?Carbon $activityUntil): int
    {
        if ($recordedItems->isEmpty()) {
            return 0;
        }

        $query = DB::table('inspection_item_measurements')
            ->whereIn('inspection_item_id', $recordedItems->pluck('id'));

        if ($activitySince !== null) {
            $query->whereBetween('created_at', [$activitySince, $activityUntil ?? now()]);
        }

        return (int) $query->count();
    }

    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     */
    private function photoCount(Collection $recordedItems, ?Carbon $activitySince, ?Carbon $activityUntil): int
    {
        if ($recordedItems->isEmpty()) {
            return 0;
        }

        $query = DB::table('inspection_item_photos')
            ->whereIn('inspection_item_id', $recordedItems->pluck('id'));

        if ($activitySince !== null) {
            $query->whereBetween('created_at', [$activitySince, $activityUntil ?? now()]);
        }

        return (int) $query->count();
    }

    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     * @return array<string, int>
     */
    private function categoryCounts(Collection $recordedItems): array
    {
        return $recordedItems
            ->groupBy(fn (InspectionItem $item): string => $item->category instanceof InspectionItemCategory
                ? $item->category->value
                : (string) $item->category)
            ->map(fn (Collection $items): int => $items->count())
            ->all();
    }

    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     * @return array{notes_only_items: int, items_with_measurements: int, items_with_photos: int, state_only_items: int}
     */
    private function frictionSignals(Collection $recordedItems, ?Carbon $activitySince, ?Carbon $activityUntil): array
    {
        if ($recordedItems->isEmpty()) {
            return [
                'notes_only_items' => 0,
                'items_with_measurements' => 0,
                'items_with_photos' => 0,
                'state_only_items' => 0,
            ];
        }

        $itemIds = $recordedItems->pluck('id');

        $measurementItemIds = DB::table('inspection_item_measurements')
            ->whereIn('inspection_item_id', $itemIds)
            ->when($activitySince !== null, fn ($query) => $query->whereBetween('created_at', [$activitySince, $activityUntil ?? now()]))
            ->distinct()
            ->pluck('inspection_item_id');

        $photoItemIds = DB::table('inspection_item_photos')
            ->whereIn('inspection_item_id', $itemIds)
            ->when($activitySince !== null, fn ($query) => $query->whereBetween('created_at', [$activitySince, $activityUntil ?? now()]))
            ->distinct()
            ->pluck('inspection_item_id');

        $notesOnly = 0;
        $withMeasurements = 0;
        $withPhotos = 0;
        $stateOnly = 0;

        foreach ($recordedItems as $item) {
            $hasNotes = filled($item->notes);
            $hasMeasurements = $measurementItemIds->contains($item->id);
            $hasPhotos = $photoItemIds->contains($item->id);
            $hasState = $item->observed_state !== InspectionObservedState::NotChecked;

            if ($hasMeasurements) {
                $withMeasurements++;
            }

            if ($hasPhotos) {
                $withPhotos++;
            }

            if ($hasNotes && ! $hasMeasurements && ! $hasPhotos) {
                $notesOnly++;
            }

            if ($hasState && ! $hasNotes && ! $hasMeasurements && ! $hasPhotos) {
                $stateOnly++;
            }
        }

        return [
            'notes_only_items' => $notesOnly,
            'items_with_measurements' => $withMeasurements,
            'items_with_photos' => $withPhotos,
            'state_only_items' => $stateOnly,
        ];
    }
}
