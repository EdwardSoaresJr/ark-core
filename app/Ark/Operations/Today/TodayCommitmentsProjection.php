<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Commitments\CommitmentType;
use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Settings\ShopDisplayTimezone;

/**
 * Open shop commitments due today or overdue — visibility before automation.
 */
final class TodayCommitmentsProjection
{
    public function summary(): TodayCommitmentsSummary
    {
        $shopNow = ShopDisplayTimezone::now();
        $startOfToday = $shopNow->copy()->startOfDay()->utc();
        $endOfToday = $shopNow->copy()->endOfDay()->utc();

        $commitments = OperationalCommitment::query()
            ->open()
            ->where('due_at', '<=', $endOfToday)
            ->with(['repairOrder.customer', 'owner'])
            ->orderBy('due_at')
            ->get();

        $dueTodayCount = 0;
        $overdueCount = 0;
        $rows = [];

        foreach ($commitments as $commitment) {
            $isOverdue = $commitment->due_at->lt($startOfToday);
            $isDueToday = ! $isOverdue;

            if ($isOverdue) {
                $overdueCount++;
            } else {
                $dueTodayCount++;
            }

            $customer = $commitment->repairOrder?->customer;
            $customerName = filled($customer?->name) ? $customer->name : 'Customer';
            $repairOrder = $commitment->repairOrder;

            $rows[] = new TodayCommitmentRow(
                id: $commitment->id,
                title: $commitment->type->titleFor($customerName),
                dueLabel: $this->dueLabel($commitment, $isOverdue, $shopNow),
                reason: $commitment->reason,
                ownerName: $commitment->owner?->name ?? 'Unassigned',
                shopRepairOrderId: (int) ($repairOrder?->repair_order_id ?? 0),
                repairOrderUrl: $repairOrder !== null
                    ? route('operations.repair-orders.show', $repairOrder)
                    : route('operations.index'),
                isOverdue: $isOverdue,
                isDueToday: $isDueToday,
            );
        }

        return new TodayCommitmentsSummary(
            dueTodayCount: $dueTodayCount,
            overdueCount: $overdueCount,
            rows: $rows,
        );
    }

    private function dueLabel(OperationalCommitment $commitment, bool $isOverdue, \Illuminate\Support\Carbon $shopNow): string
    {
        $dueAtShop = $commitment->due_at->copy()->timezone(ShopDisplayTimezone::resolve());

        if ($isOverdue) {
            if ($dueAtShop->isSameDay($shopNow->copy()->subDay())) {
                return 'Due yesterday '.$dueAtShop->format('g:i A');
            }

            return 'Overdue · '.$dueAtShop->format('M j, g:i A');
        }

        return 'Due '.$dueAtShop->format('g:i A');
    }
}
