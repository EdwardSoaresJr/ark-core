<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Approvals\ApprovalEvent;
use Illuminate\Support\Collection;

final class RepairOrderPosture
{
    /**
     * @return array{
     *     approvedConcerns: Collection<int, RepairOrderConcern>,
     *     deferredConcerns: Collection<int, RepairOrderConcern>,
     *     declinedConcerns: Collection<int, RepairOrderConcern>,
     *     recommendedConcerns: Collection<int, RepairOrderConcern>,
     *     lastApprovalEvent: ?ApprovalEvent,
     *     priorVehicleFutureWork: Collection<int, RepairOrder>,
     *     priorVehicleFutureWorkCount: int,
     *     priorVehicleFutureWorkCents: int,
     *     partsReadinessCounts: array<string, int>,
     *     partsBlockingCount: int,
     *     approvalPosture: string,
     *     nextAction: string,
     *     approvalTone: string,
     * }
     */
    public static function for(RepairOrder $repairOrder): array
    {
        $approvedConcerns = $repairOrder->concerns->where('disposition', RepairOrderConcernDisposition::Approved);
        $deferredConcerns = $repairOrder->concerns->where('disposition', RepairOrderConcernDisposition::Deferred);
        $declinedConcerns = $repairOrder->concerns->where('disposition', RepairOrderConcernDisposition::Declined);
        $recommendedConcerns = $repairOrder->concerns->where('disposition', RepairOrderConcernDisposition::Recommended);
        $lastApprovalEvent = $repairOrder->relationLoaded('approvalEvents')
            ? $repairOrder->approvalEvents->first(fn (ApprovalEvent $event): bool => ! $event->isRevoked())
            : null;
        $priorVehicleFutureWork = ($repairOrder->vehicle && $repairOrder->vehicle->relationLoaded('repairOrders'))
            ? $repairOrder->vehicle->repairOrders->filter(fn (RepairOrder $priorRepairOrder) => $priorRepairOrder->futureWorkCount() > 0)
            : collect();
        $priorVehicleFutureWorkCount = (int) $priorVehicleFutureWork->sum(fn (RepairOrder $priorRepairOrder) => $priorRepairOrder->futureWorkCount());
        $priorVehicleFutureWorkCents = (int) $priorVehicleFutureWork->sum(fn (RepairOrder $priorRepairOrder) => $priorRepairOrder->futureWorkSubtotalCents());
        $partsReadinessCounts = $repairOrder->approvedPartReadinessCounts();
        $partsBlockingCount = $partsReadinessCounts['needs_ordered']
            + $partsReadinessCounts['sourcing']
            + $partsReadinessCounts['ordered']
            + $partsReadinessCounts['partial']
            + $partsReadinessCounts['backordered'];
        $approvalPosture = match (true) {
            $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && ! $repairOrder->isPaid() => 'Balance due at pickup',
            $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && $repairOrder->isPaid() => 'Paid / ready to release',
            $repairOrder->isTerminal() => 'Delivered / archived',
            $approvedConcerns->isNotEmpty() && ($deferredConcerns->isNotEmpty() || $declinedConcerns->isNotEmpty()) => 'Mixed authorization',
            $approvedConcerns->isNotEmpty() => 'Approved work ready',
            $deferredConcerns->isNotEmpty() => 'Deferred work retained',
            $declinedConcerns->isNotEmpty() => 'Work declined',
            $repairOrder->status->is(RepairOrderStatus::WaitingApproval) => 'Customer decision pending',
            default => 'Not presented',
        };
        $nextAction = match (true) {
            $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && ! $repairOrder->isPaid() => 'Collect balance before releasing vehicle',
            $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && $repairOrder->isPaid() => 'Release vehicle and close repair order',
            $repairOrder->isTerminal() => 'Archived from active queue',
            $approvedConcerns->isNotEmpty() && $repairOrder->hasUnresolvedApprovedParts() => 'Order approved parts',
            $approvedConcerns->isNotEmpty() => 'Release approved work to production',
            $deferredConcerns->isNotEmpty() => 'Retain deferred work for follow-up',
            $declinedConcerns->isNotEmpty() => 'Declined work recorded — no follow-up on those scopes',
            $repairOrder->status->is(RepairOrderStatus::WaitingApproval) => 'Waiting customer authorization',
            $recommendedConcerns->isNotEmpty() => 'Present estimate for authorization',
            default => 'Build estimate before authorization',
        };
        $approvalTone = match (true) {
            $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && ! $repairOrder->isPaid() => 'text-amber-900 border-amber-400 bg-amber-50',
            $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && $repairOrder->isPaid() => 'text-emerald-800 border-emerald-300 bg-emerald-50',
            $repairOrder->isTerminal() => 'text-slate-700 border-slate-300 bg-slate-50',
            $repairOrder->status->is(RepairOrderStatus::WaitingApproval) => 'text-amber-800 border-amber-300 bg-amber-50',
            $approvedConcerns->isNotEmpty() => 'text-emerald-800 border-emerald-300 bg-emerald-50',
            $deferredConcerns->isNotEmpty() => 'text-slate-700 border-slate-300 bg-slate-50',
            $declinedConcerns->isNotEmpty() => 'text-rose-900 border-rose-300 bg-rose-50',
            default => 'text-slate-600 border-slate-300 bg-white',
        };

        return compact(
            'approvedConcerns',
            'deferredConcerns',
            'declinedConcerns',
            'recommendedConcerns',
            'lastApprovalEvent',
            'priorVehicleFutureWork',
            'priorVehicleFutureWorkCount',
            'priorVehicleFutureWorkCents',
            'partsReadinessCounts',
            'partsBlockingCount',
            'approvalPosture',
            'nextAction',
            'approvalTone',
        );
    }
}
