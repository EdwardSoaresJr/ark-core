<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Support\Collection;

final class InspectionWorkspaceProjection
{
    private const RECENT_FINDING_LIMIT = 8;

    /**
     * @return array{
     *     inspection: Inspection,
     *     recent_finding_rows: list<array{finding: array<string, mixed>, item: InspectionItem}>,
     *     browse_categories: list<array{category: InspectionItemCategory, rows: list<array{finding: array<string, mixed>, item: InspectionItem}>}>,
     *     concerns: Collection<int, RepairOrderConcern>,
     *     categories: list<InspectionItemCategory>,
     *     finding_intents: list<InspectionFindingIntent>,
     *     observed_states: list<InspectionObservedState>,
     *     photo_purposes: list<InspectionPhotoPurpose>,
     *     has_recorded_findings: bool,
     *     total_recorded_finding_count: int,
     *     has_older_findings: bool,
     * }
     */
    public function for(RepairOrder $repairOrder, Inspection $inspection): array
    {
        $inspection->load([
            'items.measurements',
            'items.photos',
            'items.concern',
        ]);

        $repairOrder->loadMissing('concerns');

        $recordedItems = $inspection->items
            ->filter(fn (InspectionItem $item): bool => InspectionFindingCardProjection::isRecorded($item))
            ->sortByDesc(fn (InspectionItem $item): int => $item->updated_at?->getTimestamp() ?? 0)
            ->values();

        $totalRecordedFindingCount = $recordedItems->count();

        $recentFindingRows = $recordedItems
            ->take(self::RECENT_FINDING_LIMIT)
            ->map(fn (InspectionItem $item): array => $this->findingRow($item, $repairOrder))
            ->all();

        $recentFindingIds = collect($recentFindingRows)
            ->map(fn (array $row): int => $row['item']->id)
            ->flip();

        $browseCategories = [];

        foreach (InspectionItemCategory::ordered() as $category) {
            $categoryRows = $inspection->items
                ->filter(fn (InspectionItem $item): bool => ($item->category instanceof InspectionItemCategory
                    ? $item->category->value
                    : (string) $item->category) === $category->value)
                ->reject(fn (InspectionItem $item): bool => $recentFindingIds->has($item->id))
                ->values();

            if ($categoryRows->isEmpty()) {
                continue;
            }

            $browseCategories[] = [
                'category' => $category,
                'rows' => $categoryRows
                    ->map(fn (InspectionItem $item): array => $this->findingRow($item, $repairOrder))
                    ->all(),
            ];
        }

        return [
            'inspection' => $inspection,
            'recent_finding_rows' => $recentFindingRows,
            'browse_categories' => $browseCategories,
            'concerns' => $repairOrder->concerns,
            'categories' => InspectionItemCategory::ordered(),
            'finding_intents' => InspectionFindingIntent::ordered(),
            'observed_states' => [
                InspectionObservedState::NotChecked,
                InspectionObservedState::Pass,
                InspectionObservedState::Monitor,
                InspectionObservedState::NeedsAttention,
                InspectionObservedState::Fail,
                InspectionObservedState::Measure,
                InspectionObservedState::Na,
            ],
            'photo_purposes' => [
                InspectionPhotoPurpose::Internal,
                InspectionPhotoPurpose::Customer,
                InspectionPhotoPurpose::Before,
                InspectionPhotoPurpose::After,
            ],
            'has_recorded_findings' => $totalRecordedFindingCount > 0,
            'total_recorded_finding_count' => $totalRecordedFindingCount,
            'has_older_findings' => $totalRecordedFindingCount > count($recentFindingRows),
        ];
    }

    /**
     * @return array{finding: array<string, mixed>, item: InspectionItem}
     */
    private function findingRow(InspectionItem $item, RepairOrder $repairOrder): array
    {
        return [
            'finding' => InspectionFindingCardProjection::forItem($item, $repairOrder),
            'item' => $item,
        ];
    }
}
