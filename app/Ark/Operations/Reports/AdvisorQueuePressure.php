<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Collection;

final class AdvisorQueuePressure
{
    /**
     * Live queue pressure grouped by inferred service advisor.
     *
     * @return list<array{advisor: string, queue: int, waiting_approval: int, parts: int, unpaid: int, hint: string}>
     */
    public function rows(): array
    {
        $repairOrders = RepairOrder::query()
            ->with(['encounter.creator', 'estimateDocuments.creator'])
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->tap(fn ($query) => OperationalReportDateScope::applyTrustworthyDataFloor($query))
            ->get();

        if ($repairOrders->isEmpty()) {
            return [];
        }

        return $repairOrders
            ->groupBy(fn (RepairOrder $repairOrder): string => $repairOrder->serviceAdvisorName() ?? 'Unassigned')
            ->map(function (Collection $group, string $advisor): array {
                $waitingApproval = $group->where('status', RepairOrderStatus::WaitingApproval);
                $waitingParts = $group->filter(
                    fn (RepairOrder $repairOrder): bool => $repairOrder->workboardLaneStatus() === RepairOrderStatus::WaitingParts,
                );
                $unpaidPickups = $group->filter(
                    fn (RepairOrder $repairOrder): bool => $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && ! $repairOrder->isPaid(),
                );

                $hints = collect([
                    $waitingApproval->isNotEmpty() ? $waitingApproval->count().' awaiting approval' : null,
                    $waitingParts->isNotEmpty() ? $waitingParts->count().' parts backlog' : null,
                    $unpaidPickups->isNotEmpty() ? $unpaidPickups->count().' unpaid pickup' : null,
                ])->filter()->values();

                return [
                    'advisor' => $advisor,
                    'queue' => $group->count(),
                    'waiting_approval' => $waitingApproval->count(),
                    'parts' => $waitingParts->count(),
                    'unpaid' => $unpaidPickups->count(),
                    'hint' => $hints->isEmpty() ? 'Queue moving' : $hints->join(' · '),
                ];
            })
            ->sortByDesc('queue')
            ->values()
            ->all();
    }
}
