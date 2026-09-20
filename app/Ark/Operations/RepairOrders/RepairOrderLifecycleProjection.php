<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class RepairOrderLifecycleProjection
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     occurred_at: ?Carbon,
     *     source: string,
     *     status: 'complete'|'pending'|'derived',
     *     note: ?string,
     * }>
     */
    public function for(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['approvalEvents', 'approvalEvents.revocation', 'communicationEvents', 'lines.concern']);

        $operationalEvents = $this->operationalEvents($repairOrder);

        return [
            $this->created($repairOrder),
            $this->estimateSent($repairOrder),
            $this->estimateViewed($repairOrder),
            $this->approved($repairOrder),
            $this->partsOrdered($operationalEvents),
            $this->partsReceived($repairOrder, $operationalEvents),
            $this->workStarted($operationalEvents),
            $this->workCompleted($operationalEvents),
            $this->pickupNotified($repairOrder),
            $this->closed($operationalEvents),
        ];
    }

    /**
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function created(RepairOrder $repairOrder): array
    {
        $occurredAt = $repairOrder->created_at;

        return $this->row(
            key: 'created',
            label: 'Created',
            occurredAt: $occurredAt,
            source: 'repair_orders.created_at',
            status: $occurredAt !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function estimateSent(RepairOrder $repairOrder): array
    {
        $occurredAt = $this->firstCommunicationAt($repairOrder, OperationalCommunicationType::EstimateSent);

        return $this->row(
            key: 'estimate_sent',
            label: 'Estimate sent',
            occurredAt: $occurredAt,
            source: 'communication_events.estimate_sent',
            status: $occurredAt !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function estimateViewed(RepairOrder $repairOrder): array
    {
        $occurredAt = $this->firstCommunicationAt($repairOrder, OperationalCommunicationType::EstimateViewed);

        return $this->row(
            key: 'estimate_viewed',
            label: 'Estimate viewed',
            occurredAt: $occurredAt,
            source: 'communication_events.estimate_viewed',
            status: $occurredAt !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function approved(RepairOrder $repairOrder): array
    {
        $approval = $this->firstMeaningfulApproval($repairOrder);

        return $this->row(
            key: 'approved',
            label: 'Approved',
            occurredAt: $approval?->approved_at,
            source: 'approval_events.approved_at',
            status: $approval !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @param  Collection<int, OperationalEvent>  $operationalEvents
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function partsOrdered(Collection $operationalEvents): array
    {
        $event = $this->firstOperationalEvent($operationalEvents, OperationalEventName::PartOrdered);

        return $this->row(
            key: 'parts_ordered',
            label: 'Parts ordered',
            occurredAt: $event?->occurred_at,
            source: 'operational_events.part_ordered',
            status: $event !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @param  Collection<int, OperationalEvent>  $operationalEvents
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function partsReceived(RepairOrder $repairOrder, Collection $operationalEvents): array
    {
        $approvedPartLines = $repairOrder->approvedPartLines();

        if ($approvedPartLines->isEmpty()) {
            return $this->row(
                key: 'parts_received',
                label: 'Parts received',
                occurredAt: null,
                source: 'derived.approved_part_lines',
                status: 'pending',
                note: 'No approved parts required',
            );
        }

        if ($repairOrder->hasUnresolvedApprovedParts()) {
            $summary = $repairOrder->partsBlockerSummary();

            return $this->row(
                key: 'parts_received',
                label: 'Parts received',
                occurredAt: null,
                source: 'derived.approved_part_lines',
                status: 'pending',
                note: $summary !== null ? 'Waiting on '.$summary : 'Approved parts not fully received',
            );
        }

        $finalReceivedAt = $this->latestOperationalEventAt(
            $operationalEvents,
            OperationalEventName::PartReceived,
        );

        if ($finalReceivedAt === null) {
            return $this->row(
                key: 'parts_received',
                label: 'Parts received',
                occurredAt: null,
                source: 'derived.approved_part_lines',
                status: 'derived',
                note: 'All approved parts resolved without a receive event',
            );
        }

        return $this->row(
            key: 'parts_received',
            label: 'Parts received',
            occurredAt: $finalReceivedAt,
            source: 'operational_events.part_received',
            status: 'derived',
        );
    }

    /**
     * @param  Collection<int, OperationalEvent>  $operationalEvents
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function workStarted(Collection $operationalEvents): array
    {
        $event = $this->firstLifecycleTransition($operationalEvents, RepairOrderStatus::InProgress);

        return $this->row(
            key: 'work_started',
            label: 'Work started',
            occurredAt: $event?->occurred_at,
            source: 'operational_events.repair_order_lifecycle_changed.in_progress',
            status: $event !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @param  Collection<int, OperationalEvent>  $operationalEvents
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function workCompleted(Collection $operationalEvents): array
    {
        $event = $this->firstLifecycleTransition($operationalEvents, RepairOrderStatus::ReadyPickup);

        return $this->row(
            key: 'work_completed',
            label: 'Work completed',
            occurredAt: $event?->occurred_at,
            source: 'operational_events.repair_order_lifecycle_changed.ready_pickup',
            status: $event !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function pickupNotified(RepairOrder $repairOrder): array
    {
        $occurredAt = $this->firstCommunicationAt($repairOrder, OperationalCommunicationType::PickupNotified);

        return $this->row(
            key: 'pickup_notified',
            label: 'Pickup notified',
            occurredAt: $occurredAt,
            source: 'communication_events.pickup_notified',
            status: $occurredAt !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @param  Collection<int, OperationalEvent>  $operationalEvents
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function closed(Collection $operationalEvents): array
    {
        $event = $this->firstLifecycleTransition($operationalEvents, RepairOrderStatus::Closed);

        return $this->row(
            key: 'closed',
            label: 'Closed',
            occurredAt: $event?->occurred_at,
            source: 'operational_events.repair_order_lifecycle_changed.closed',
            status: $event !== null ? 'complete' : 'pending',
        );
    }

    /**
     * @return Collection<int, OperationalEvent>
     */
    private function operationalEvents(RepairOrder $repairOrder): Collection
    {
        return OperationalEvent::query()
            ->where('aggregate_type', RepairOrder::class)
            ->where('aggregate_id', $repairOrder->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    private function firstCommunicationAt(RepairOrder $repairOrder, OperationalCommunicationType $type): ?Carbon
    {
        return $repairOrder->communicationEvents
            ->filter(fn (CommunicationEvent $event): bool => $event->event_type === $type)
            ->sortBy(fn (CommunicationEvent $event): int => $event->occurred_at?->getTimestamp() ?? PHP_INT_MAX)
            ->first()
            ?->occurred_at;
    }

    private function firstMeaningfulApproval(RepairOrder $repairOrder): ?ApprovalEvent
    {
        return $repairOrder->approvalEvents
            ->filter(fn (ApprovalEvent $event): bool => (int) $event->approved_amount_cents > 0 && ! $event->isRevoked())
            ->sortBy(fn (ApprovalEvent $event): int => $event->approved_at?->getTimestamp() ?? PHP_INT_MAX)
            ->first();
    }

    /**
     * @param  Collection<int, OperationalEvent>  $operationalEvents
     */
    private function firstOperationalEvent(Collection $operationalEvents, OperationalEventName $name): ?OperationalEvent
    {
        return $operationalEvents
            ->first(fn (OperationalEvent $event): bool => $event->event_name === $name->value);
    }

    /**
     * @param  Collection<int, OperationalEvent>  $operationalEvents
     */
    private function latestOperationalEventAt(Collection $operationalEvents, OperationalEventName $name): ?Carbon
    {
        return $operationalEvents
            ->filter(fn (OperationalEvent $event): bool => $event->event_name === $name->value)
            ->sortByDesc(fn (OperationalEvent $event): int => $event->occurred_at?->getTimestamp() ?? 0)
            ->first()
            ?->occurred_at;
    }

    /**
     * @param  Collection<int, OperationalEvent>  $operationalEvents
     */
    private function firstLifecycleTransition(Collection $operationalEvents, RepairOrderStatus $toStatus): ?OperationalEvent
    {
        return $operationalEvents->first(function (OperationalEvent $event) use ($toStatus): bool {
            if ($event->event_name !== OperationalEventName::RepairOrderLifecycleChanged->value) {
                return false;
            }

            return ($event->payload_json['to_status'] ?? null) === $toStatus->value;
        });
    }

    /**
     * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: 'complete'|'pending'|'derived', note: ?string}
     */
    private function row(
        string $key,
        string $label,
        ?Carbon $occurredAt,
        string $source,
        string $status,
        ?string $note = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'occurred_at' => $occurredAt,
            'source' => $source,
            'status' => $status,
            'note' => $note,
        ];
    }
}
