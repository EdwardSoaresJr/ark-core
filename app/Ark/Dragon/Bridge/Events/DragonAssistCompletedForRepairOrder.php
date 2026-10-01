<?php

namespace App\Ark\Dragon\Bridge\Events;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Advisors on the RO workspace may receive Dragon Assist enrichment without refresh.
 */
final class DragonAssistCompletedForRepairOrder implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public DragonAssistRequest $request) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $displayId = (int) ($this->request->repairOrder?->repair_order_id ?? 0);

        if ($displayId <= 0) {
            return [];
        }

        return [
            new PrivateChannel('operations.repair-orders.'.$displayId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dragon.assist.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $result = $this->request->result;

        return [
            'request_id' => $this->request->public_id,
            'task_type' => $this->request->task_type->value,
            'status' => $this->request->status->value,
            'result' => $result?->result_json,
        ];
    }
}
