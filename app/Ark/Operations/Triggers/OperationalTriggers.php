<?php

namespace App\Ark\Operations\Triggers;

use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Collection;

class OperationalTriggers
{
    /**
     * @return Collection<int, array{label: string, action: string, tone: string, age: string}>
     */
    public function forRepairOrder(RepairOrder $repairOrder): Collection
    {
        $repairOrder->loadMissing(['communicationEvents', 'lines.concern']);

        return collect([
            $this->approvalTrigger($repairOrder),
            $this->partsTrigger($repairOrder),
            $this->pickupTrigger($repairOrder),
            $this->dispatchTrigger($repairOrder),
            $this->stalledWorkTrigger($repairOrder),
        ])->filter()->values();
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, array{repair_order: RepairOrder, trigger: array{label: string, action: string, tone: string, age: string}}>
     */
    public function pressure(Collection $repairOrders, int $limit = 8): Collection
    {
        return $repairOrders
            ->flatMap(fn (RepairOrder $repairOrder): Collection => $this->forRepairOrder($repairOrder)
                ->map(fn (array $trigger): array => [
                    'repair_order' => $repairOrder,
                    'trigger' => $trigger,
                ]))
            ->sortByDesc(fn (array $entry): int => match ($entry['trigger']['tone']) {
                'blocked' => 4,
                'approval' => 3,
                'pickup' => 2,
                default => 1,
            })
            ->take($limit)
            ->values();
    }

    /**
     * @return array{label: string, action: string, tone: string, age: string}|null
     */
    private function approvalTrigger(RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return null;
        }

        $lastEvent = $repairOrder->latestCommunicationEvent();

        if ($lastEvent === null) {
            return $this->trigger('Approval contact needed', 'Send estimate or call for authorization', 'approval', $repairOrder);
        }

        if ($lastEvent->event_type === OperationalCommunicationType::EstimateViewed && $lastEvent->occurred_at?->lt(now()->subHours(2))) {
            return $this->trigger('Viewed estimate aging', 'Follow up viewed estimate', 'approval', $repairOrder);
        }

        if (in_array($lastEvent->event_type, [
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationType::ApprovalFollowUp,
        ], true) && $lastEvent->occurred_at?->lt(now()->subHours(4))) {
            return $this->trigger('Approval follow-up due', 'Check customer response before estimate stalls', 'approval', $repairOrder);
        }

        if ($repairOrder->updated_at->lt(now()->subHours(4))) {
            return $this->trigger('Approval aging', 'Review authorization next step', 'approval', $repairOrder);
        }

        return null;
    }

    /**
     * @return array{label: string, action: string, tone: string, age: string}|null
     */
    private function partsTrigger(RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder->hasUnresolvedApprovedParts()) {
            return null;
        }

        $summary = $repairOrder->partsBlockerSummary() ?: 'Parts blocker';

        if ($repairOrder->updated_at->lt(now()->subHours(4))) {
            return $this->trigger('Parts blocker aging', 'Confirm ETA and clear procurement blocker: '.$summary, 'blocked', $repairOrder);
        }

        return $this->trigger('Parts follow-up active', $repairOrder->procurementNextActionSummary() ?: 'Keep procurement moving', 'blocked', $repairOrder);
    }

    /**
     * @return array{label: string, action: string, tone: string, age: string}|null
     */
    private function pickupTrigger(RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder->status->is(RepairOrderStatus::ReadyPickup)) {
            return null;
        }

        $lastEvent = $repairOrder->latestCommunicationEvent();

        if ($lastEvent?->event_type !== OperationalCommunicationType::PickupNotified) {
            return $this->trigger('Pickup notification due', 'Notify customer pickup ready', 'pickup', $repairOrder);
        }

        if (! $repairOrder->isPaid()) {
            return $this->trigger('Unpaid pickup aging', 'Collect balance before release', 'pickup', $repairOrder);
        }

        if ($lastEvent->occurred_at?->lt(now()->subHours(4))) {
            return $this->trigger('Pickup follow-up due', 'Confirm arrival timing', 'pickup', $repairOrder);
        }

        return null;
    }

    /**
     * @return array{label: string, action: string, tone: string, age: string}|null
     */
    private function dispatchTrigger(RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder->status->isOneOf([RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork])) {
            return null;
        }

        if ($repairOrder->assigned_technician_id !== null || $repairOrder->hasUnresolvedApprovedParts()) {
            return null;
        }

        return $this->trigger('Dispatch assignment due', 'Assign technician before execution stalls', 'motion', $repairOrder);
    }

    /**
     * @return array{label: string, action: string, tone: string, age: string}|null
     */
    private function stalledWorkTrigger(RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder->status->isOneOf([RepairOrderStatus::Draft, RepairOrderStatus::Estimate, RepairOrderStatus::InProgress])) {
            return null;
        }

        if (! $repairOrder->updated_at->lt(now()->subHours(4))) {
            return null;
        }

        return $this->trigger('Workflow stalled', $repairOrder->executionNextAction(), 'stalled', $repairOrder);
    }

    /**
     * @return array{label: string, action: string, tone: string, age: string}
     */
    private function trigger(string $label, string $action, string $tone, RepairOrder $repairOrder): array
    {
        return [
            'label' => $label,
            'action' => $action,
            'tone' => $tone,
            'age' => str_replace(' ago', '', $repairOrder->updated_at->diffForHumans(short: true, parts: 1)),
        ];
    }
}
