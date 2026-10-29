<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RepairOrderFinancialChanged implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Sent by a queue worker once the change is committed, so the browser's refresh sees it. */
    public bool $afterCommit = true;

    public function __construct(
        public RepairOrder $repairOrder,
        public string $reason = 'updated',
        public ?int $actorId = null,
        public ?int $balanceDueCents = null,
    ) {}

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
        return 'financial.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'repair_order_id' => (int) $this->repairOrder->repair_order_id,
            'reason' => $this->reason,
            'actor_id' => $this->actorId,
            'balance_due_cents' => $this->balanceDueCents,
        ];
    }
}
