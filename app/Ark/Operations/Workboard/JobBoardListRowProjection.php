<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\PartsPressure;
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
                    ?->owner_user_id;
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
                    updatedLabel: $updatedAt->diffForHumans(short: true, parts: 1),
                    updatedAtTimestamp: $updatedAt->getTimestamp(),
                    searchHaystack: $searchHaystack,
                );
            }
        }

        return collect($rows)
            ->sortBy([
                fn (JobBoardListRow $row): int => -($row->card->pressureScore),
                fn (JobBoardListRow $row): int => -$row->card->ageMinutes,
            ])
            ->values()
            ->all();
    }

    /**
     * Tab counts use the same lane totals as the board columns (not raw triage queue size).
     *
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @return list<array{key: string, label: string, color: string, count: int}>
     */
    public function laneTabs(array $homeBoardColumns): array
    {
        $laneTotal = collect($homeBoardColumns)->sum(fn (WorkboardTriageLaneProjection $column): int => $column->totalCount);

        $tabs = [[
            'key' => 'all',
            'label' => 'All',
            'color' => 'secondary',
            'count' => $laneTotal,
        ]];

        foreach ($homeBoardColumns as $column) {
            $tabs[] = [
                'key' => $column->key,
                'label' => $column->label,
                'color' => $column->color,
                'count' => $column->totalCount,
            ];
        }

        return $tabs;
    }
}
