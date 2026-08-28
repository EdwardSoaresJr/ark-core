<?php

namespace App\Ark\Mobile\Push;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Lifecycle pushes from operational events — parts, vehicle ready, waiting parts.
 */
final class DispatchMobilePushForOperationalEvent implements ShouldQueue
{
    public function __construct(
        private readonly NotifyMobileLifecyclePushAction $notify,
        private readonly MobileAwarePushCopy $copy,
    ) {}

    public function handle(OperationalEvent $event): void
    {
        $name = $event->event_name instanceof OperationalEventName
            ? $event->event_name
            : OperationalEventName::tryFrom((string) $event->event_name);

        if ($name === null) {
            return;
        }

        match ($name) {
            OperationalEventName::PartReceived => $this->handlePartReceived($event),
            OperationalEventName::RepairOrderLifecycleChanged => $this->handleLifecycleChanged($event),
            default => null,
        };
    }

    private function handlePartReceived(OperationalEvent $event): void
    {
        $repairOrder = $this->resolveRepairOrder($event);

        if ($repairOrder === null) {
            return;
        }

        $payload = is_array($event->payload_json) ? $event->payload_json : [];
        $lineId = isset($payload['repair_order_line_id']) ? (int) $payload['repair_order_line_id'] : null;
        $partLabel = $this->copy->partLabelFromLineId($lineId);

        $this->notify->forPartsArrived($repairOrder, $partLabel);
    }

    private function handleLifecycleChanged(OperationalEvent $event): void
    {
        $repairOrder = $this->resolveRepairOrder($event);

        if ($repairOrder === null) {
            return;
        }

        $payload = is_array($event->payload_json) ? $event->payload_json : [];
        $toStatus = (string) ($payload['to_status'] ?? '');

        if ($toStatus === RepairOrderStatus::ReadyPickup->value) {
            $this->notify->forVehicleReady($repairOrder);

            return;
        }

        if ($toStatus === RepairOrderStatus::WaitingParts->value) {
            $this->notify->forWaitingParts($repairOrder);
        }
    }

    private function resolveRepairOrder(OperationalEvent $event): ?RepairOrder
    {
        if ($event->aggregate_type !== RepairOrder::class) {
            return null;
        }

        return RepairOrder::query()
            ->with(['customer', 'vehicle'])
            ->find($event->aggregate_id);
    }
}
