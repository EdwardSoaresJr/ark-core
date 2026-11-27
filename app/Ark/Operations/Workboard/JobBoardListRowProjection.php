<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\PartsPressure;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Today\AdvisorHomeCardSurface;
use Illuminate\Support\Collection;

final class JobBoardListRowProjection
{
    /**
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @param  array<int, AdvisorHomeCardSurface>  $homeCardSurfaces
     * @return list<JobBoardListRow>
     */
    public function fromHomeBoard(array $homeBoardColumns, array $homeCardSurfaces): array
    {
        $rows = [];

        foreach ($homeBoardColumns as $column) {
            foreach ($column->visibleCards as $card) {
                $repairOrder = $card->repairOrder;
                $surface = $homeCardSurfaces[$repairOrder->id] ?? null;

                if (! $surface instanceof AdvisorHomeCardSurface) {
                    continue;
                }

                $technicianName = $repairOrder->repairActionOwnerSummary()
                    ?? (filled($repairOrder->assignedTechnician?->name)
                        ? (string) $repairOrder->assignedTechnician->name
                        : null);
                $technicianId = $repairOrder->concerns
                    ->flatMap->workGroups
                    ->first(fn ($group) => $group->hasOwner())
                    ?->owner_user_id
                    ?? $repairOrder->assigned_technician_id;
                $parts = $repairOrder->partsPressure();
                $partsLabel = $parts->showsChip()
                    ? ($repairOrder->partsPressureSummary($parts) ?? $parts->label())
                    : ($parts === PartsPressure::AllPartsAvailable ? 'Ready' : null);
                $updatedAt = ShopDisplayTimezone::present($repairOrder->updated_at);
                $customerName = trim((string) ($repairOrder->customer?->name ?? ''));
                $statusLabel = filled($surface->chip->label) ? $surface->chip->label : 'No Status';
                $searchHaystack = strtolower(collect([
                    $repairOrder->repair_order_id,
                    $customerName,
                    $card->vehicleLabel,
                    $surface->concernLabel,
                    $card->concernHeadline,
                    $statusLabel,
                    $technicianName,
                    $surface->promiseLabel,
                    $partsLabel,
                    $surface->exceptionLabel,
                    $column->label,
                ])->filter()->join(' '));

                $rows[] = new JobBoardListRow(
                    card: $card,
                    surface: $surface,
                    laneKey: $column->key,
                    laneLabel: $column->label,
                    laneColor: $column->color,
                    technicianName: $technicianName,
                    technicianId: $technicianId !== null ? (string) $technicianId : null,
                    partsLabel: $partsLabel,
                    moneyLabel: $surface->moneyLabel,
                    moneyCaption: $surface->moneyCaption,
                    activityMarks: $surface->activityMarks,
                    updatedLabel: $updatedAt->diffForHumans(short: true, parts: 1),
                    updatedAtTimestamp: $updatedAt->getTimestamp(),
                    searchHaystack: $searchHaystack,
                );
            }
        }

        // Preserve the page order chosen by JobBoardListPageBuilder (already sorted).
        return array_values($rows);
    }

    /**
     * Exact queue totals per lane (independent of the current page / search filter).
     *
     * @param  list<array{key: string, label: string, color?: string, tone?: string}>  $laneMeta
     * @param  Collection<string, Collection<int, RepairOrder>>  $byLane
     * @return list<array{key: string, label: string, color: string, count: int}>
     */
    public function laneTabsFromCounts(array $laneMeta, Collection $byLane): array
    {
        $laneTotal = $byLane->sum(fn (Collection $orders): int => $orders->count());

        $tabs = [[
            'key' => JobBoardLaneTabPresentation::ALL_KEY,
            'label' => 'All',
            // System aggregate accent — not a repair-order status / lane setting.
            'color' => JobBoardLaneTabPresentation::ALL_ACCENT,
            'count' => $laneTotal,
        ]];

        foreach ($laneMeta as $column) {
            $key = $column['key'];
            $tabs[] = [
                'key' => $key,
                'label' => $column['label'],
                'color' => JobBoardLaneTabPresentation::colorForTab($key, $column['color'] ?? null),
                'count' => ($byLane->get($key) ?? collect())->count(),
            ];
        }

        return $tabs;
    }
}
