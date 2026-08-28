<?php

namespace App\Ark\Operations\Today\Lifecycle;

use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\PartsPressure;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Today\Surface\TodayLens;
use App\Ark\Operations\Today\Surface\TodayOwnerResolver;
use Illuminate\Support\Carbon;

final class PartsArrivalLifecycle implements TodayRecommendationLifecycle
{
    public function __construct(
        private readonly TodayOwnerResolver $owners,
    ) {}

    public function kind(): TodayRecommendationKind
    {
        return TodayRecommendationKind::PartsArrival;
    }

    public function completionAuthority(): TodayCompletionAuthority
    {
        return TodayCompletionAuthority::Inventory;
    }

    public function completionEvents(): array
    {
        return [
            TodayCompletionEvent::PartReceived,
            TodayCompletionEvent::PartInstalled,
        ];
    }

    public function candidates(BriefingContext $context, TodayLens $lens): array
    {
        if ($lens === TodayLens::Technician) {
            return [];
        }

        $ownerLabel = match ($lens) {
            TodayLens::Owner => $this->owners->defaultAdvisorLabel(),
            TodayLens::Advisor => $this->owners->forUser($context->user),
            TodayLens::Technician => $this->owners->defaultAdvisorLabel(),
        };

        $sectionKey = 'waiting_parts';

        $candidates = [];

        foreach ($this->pressuredRepairOrders() as $pressure) {
            $repairOrder = $pressure['repair_order'];
            $vehicle = $this->vehicleLabel($repairOrder);

            $candidates[] = new TodayLifecycleCandidate(
                kind: $this->kind(),
                instanceKey: $this->kind()->value.'_'.$repairOrder->repair_order_id,
                title: 'Parts arrived · '.$vehicle,
                ownerLabel: $ownerLabel,
                url: route('operations.repair-orders.show', $repairOrder),
                whyYouLabel: 'You coordinate parts receiving and scheduling.',
                expectedOutcome: 'Technician can resume work.',
                reason: $pressure['reason'],
                effortLabel: null,
                aggregateType: RepairOrder::class,
                aggregateId: (int) $repairOrder->repair_order_id,
                pressureSince: $pressure['pressure_since'],
                sortWeight: $pressure['sort_weight'],
                sectionKey: $sectionKey,
            );
        }

        return $candidates;
    }

    public function retirementFromOperationalEvent(OperationalEvent $event): ?RecommendationResolutionRetirement
    {
        if ($event->aggregate_type !== RepairOrder::class) {
            return null;
        }

        $repairOrder = RepairOrder::query()
            ->with(['customer', 'vehicle', 'concerns.lines'])
            ->whereKey($event->aggregate_id)
            ->first();

        if ($repairOrder === null) {
            return null;
        }

        $completionEvent = match ($event->event_name) {
            OperationalEventName::PartReceived->value => TodayCompletionEvent::PartReceived,
            OperationalEventName::PartInstalled->value => TodayCompletionEvent::PartInstalled,
            OperationalEventName::RepairOrderLifecycleChanged->value => $this->completionFromLifecycle($event),
            default => null,
        };

        if ($completionEvent === null) {
            return null;
        }

        $pressure = $this->pressureBeforeEvent($repairOrder, $event);

        if ($pressure === null) {
            return null;
        }

        if ($this->isActive($repairOrder)) {
            return null;
        }

        return new RecommendationResolutionRetirement(
            kind: $this->kind(),
            aggregateType: RepairOrder::class,
            aggregateId: (int) $repairOrder->repair_order_id,
            completedByUserId: $event->actor_user_id,
            completionEvent: $completionEvent,
            outcomeLabel: $this->outcomeLabel($completionEvent),
            titleSnapshot: 'Parts arrived · '.$this->vehicleLabel($repairOrder),
            pressureSince: $pressure['pressure_since'],
            completedAt: $event->occurred_at ?? now(),
        );
    }

    public function retireReason(TodayCompletionEvent $event): string
    {
        return match ($event) {
            TodayCompletionEvent::PartReceived => 'Inventory authority recorded parts received.',
            TodayCompletionEvent::PartInstalled => 'All required parts installed on the repair order.',
            default => 'Parts pressure cleared in inventory authority.',
        };
    }

    public function isActive(RepairOrder $repairOrder): bool
    {
        return $this->pressureFor($repairOrder) !== null;
    }

    /**
     * @return list<array{repair_order: RepairOrder, reason: string, pressure_since: Carbon, sort_weight: int}>
     */
    private function pressuredRepairOrders(): array
    {
        $pressured = [];

        $repairOrders = RepairOrder::query()
            ->with(['customer', 'vehicle', 'concerns.lines'])
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->get();

        foreach ($repairOrders as $repairOrder) {
            $pressure = $this->pressureFor($repairOrder);

            if ($pressure === null) {
                continue;
            }

            $pressured[] = $pressure;
        }

        return $pressured;
    }

    /**
     * @return array{repair_order: RepairOrder, reason: string, pressure_since: Carbon, sort_weight: int}|null
     */
    private function pressureFor(RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder->hasUnresolvedApprovedParts()) {
            return null;
        }

        $awaitingReceive = $repairOrder->approvedPartLines()
            ->filter(fn (RepairOrderLine $line): bool => $line->hasUnresolvedProcurement()
                && in_array($line->procurementState(), [
                    PartProcurementState::Sourcing,
                    PartProcurementState::Ordered,
                    PartProcurementState::Partial,
                    PartProcurementState::Backordered,
                ], true));

        if ($awaitingReceive->isEmpty()) {
            return null;
        }

        $partsPressure = $repairOrder->partsPressure();

        if (! in_array($partsPressure, [
            PartsPressure::WaitingParts,
            PartsPressure::PartialParts,
            PartsPressure::Backordered,
        ], true)) {
            return null;
        }

        $oldestUnresolved = $awaitingReceive
            ->sortBy(fn (RepairOrderLine $line) => $line->updated_at?->timestamp ?? PHP_INT_MAX)
            ->first();

        $pressureSince = $oldestUnresolved?->updated_at ?? $repairOrder->updated_at ?? now();

        $reason = match ($partsPressure) {
            PartsPressure::PartialParts => 'Partial delivery — finish receiving',
            PartsPressure::Backordered => 'Vendor shipment may be ready to receive',
            default => $awaitingReceive->count().' part line'.($awaitingReceive->count() === 1 ? '' : 's').' awaiting receive',
        };

        return [
            'repair_order' => $repairOrder,
            'reason' => $reason,
            'pressure_since' => $pressureSince,
            'sort_weight' => $partsPressure === PartsPressure::PartialParts
                ? BriefingPriority::High->weight()
                : BriefingPriority::Normal->weight(),
        ];
    }

    /**
     * @return array{repair_order: RepairOrder, reason: string, pressure_since: Carbon, sort_weight: int}|null
     */
    private function pressureBeforeEvent(RepairOrder $repairOrder, OperationalEvent $event): ?array
    {
        $payload = $event->payload_json ?? [];

        if (in_array($event->event_name, [
            OperationalEventName::PartReceived->value,
            OperationalEventName::PartInstalled->value,
        ], true)) {
            $fromState = (string) ($payload['from_state'] ?? '');

            if (in_array($fromState, [
                PartProcurementState::Sourcing->value,
                PartProcurementState::Ordered->value,
                PartProcurementState::Partial->value,
                PartProcurementState::Backordered->value,
                PartProcurementState::AwaitingCustomer->value,
            ], true)) {
                return [
                    'repair_order' => $repairOrder,
                    'reason' => 'Awaiting receive',
                    'pressure_since' => $repairOrder->updated_at ?? ($event->occurred_at ?? now()),
                    'sort_weight' => BriefingPriority::Normal->weight(),
                ];
            }
        }

        return $this->pressureFor($repairOrder);
    }

    private function completionFromLifecycle(OperationalEvent $event): ?TodayCompletionEvent
    {
        $payload = $event->payload_json ?? [];
        $toStatus = (string) ($payload['to_status'] ?? '');

        if (in_array($toStatus, [
            RepairOrderStatus::ReadyForWork->value,
            RepairOrderStatus::InProgress->value,
            RepairOrderStatus::Approved->value,
        ], true)) {
            return TodayCompletionEvent::PartReceived;
        }

        return null;
    }

    private function outcomeLabel(TodayCompletionEvent $event): string
    {
        return match ($event) {
            TodayCompletionEvent::PartReceived => 'Parts received',
            TodayCompletionEvent::PartInstalled => 'Parts installed',
            default => 'Parts pressure cleared',
        };
    }

    private function vehicleLabel(RepairOrder $repairOrder): string
    {
        $vehicle = $repairOrder->vehicle;

        if ($vehicle === null) {
            return 'RO #'.$repairOrder->repair_order_id;
        }

        return trim(implode(' ', array_filter([
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
        ]))) ?: 'RO #'.$repairOrder->repair_order_id;
    }
}
