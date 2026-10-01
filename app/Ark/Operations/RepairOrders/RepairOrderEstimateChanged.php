<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RepairOrderEstimateChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public RepairOrder $repairOrder) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(RepairOrderEstimateBroadcast::channelName((int) $this->repairOrder->repair_order_id)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'estimate.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $repairOrder = $this->repairOrder->loadMissing('estimateVersionActor:id,name');
        $conflict = new RepairOrderEstimateConflictException($repairOrder);

        return [
            'repair_order_id' => (int) $repairOrder->repair_order_id,
            'estimate_version' => (int) $repairOrder->estimate_version,
            'message' => $conflict->conflictMessage(),
            'changed_by' => $repairOrder->estimateVersionActor?->name,
            'changed_at' => $repairOrder->estimate_version_at?->toIso8601String(),
            'actor_id' => $repairOrder->estimate_version_actor_id,
        ];
    }
}
