<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Collection;

final class ShopBehaviorPulse
{
    /**
     * Live queue pressure — counts and operational hints, not revenue.
     *
     * @return list<array{label: string, count: int, hint: string, tone: string}>
     */
    public function priorities(): array
    {
        $repairOrders = $this->activeRepairOrders();
        $totalsByRepairOrder = OperationalReportTotals::totalCentsByRepairOrderId($repairOrders->pluck('id'));
        $money = fn (int $cents): string => '$'.number_format($cents / 100, 2);

        $waitingApproval = $repairOrders->where('status', RepairOrderStatus::WaitingApproval);
        $waitingParts = $repairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $repairOrder->workboardLaneStatus()->is(RepairOrderStatus::WaitingParts),
        );
        $unpaidPickups = $repairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && ! $repairOrder->isPaid(),
        );
        $unassigned = $repairOrders
            ->whereIn('status', [RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork, RepairOrderStatus::InProgress])
            ->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->assigned_technician_id === null);
        $stalled = $repairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $repairOrder->updated_at->lt(now()->subHours(4)),
        );

        $sum = fn (Collection $collection): int => (int) $collection->sum(
            fn (RepairOrder $repairOrder): int => (int) ($totalsByRepairOrder[$repairOrder->id] ?? 0),
        );

        return [
            [
                'label' => 'Waiting approval',
                'count' => $waitingApproval->count(),
                'hint' => $waitingApproval->isEmpty() ? 'No authorization drag' : $money($sum($waitingApproval)).' · follow up',
                'tone' => $waitingApproval->isNotEmpty() ? 'approval' : 'calm',
            ],
            [
                'label' => 'Parts backlog',
                'count' => $waitingParts->count(),
                'hint' => $waitingParts->isEmpty() ? 'No procurement blockers' : 'Clear before bay promises',
                'tone' => $waitingParts->isNotEmpty() ? 'blocked' : 'calm',
            ],
            [
                'label' => 'Unpaid pickup',
                'count' => $unpaidPickups->count(),
                'hint' => $unpaidPickups->isEmpty() ? 'Pickups collected' : $money($sum($unpaidPickups)).' · collect before keys',
                'tone' => $unpaidPickups->isNotEmpty() ? 'cash' : 'calm',
            ],
            [
                'label' => 'Unassigned work',
                'count' => $unassigned->count(),
                'hint' => $unassigned->isEmpty() ? 'Technicians named on active jobs' : 'Assign and dispatch',
                'tone' => $unassigned->isNotEmpty() ? 'motion' : 'calm',
            ],
            [
                'label' => 'Stalled 4h+',
                'count' => $stalled->count(),
                'hint' => $stalled->isEmpty() ? 'Queue is moving' : 'Name owner and next move',
                'tone' => $stalled->isNotEmpty() ? 'blocked' : 'calm',
            ],
        ];
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    private function activeRepairOrders(): Collection
    {
        return RepairOrder::query()
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->tap(fn ($query) => OperationalReportDateScope::applyTrustworthyDataFloor($query))
            ->get();
    }
}
