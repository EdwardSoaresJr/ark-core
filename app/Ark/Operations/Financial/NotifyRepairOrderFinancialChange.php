<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateBroadcast;
use App\Ark\Operations\RepairOrders\RepairOrderFinancialChanged;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

final class NotifyRepairOrderFinancialChange
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    public function notify(RepairOrder $repairOrder, string $reason = 'updated', ?User $actor = null): void
    {
        if (! RepairOrderEstimateBroadcast::enabled()) {
            return;
        }

        $repairOrder = $repairOrder->fresh();
        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        try {
            RepairOrderFinancialChanged::dispatch(
                $repairOrder,
                $reason,
                $actor?->id,
                $balance->balanceDueCents,
            );
        } catch (\Throwable $exception) {
            if (! $exception instanceof BroadcastException && ! $this->isBroadcastTransportFailure($exception)) {
                throw $exception;
            }

            // The ledger row is already committed. A dead broadcast must not turn that into a failed save.
            Log::warning('Financial broadcast failed after the ledger committed.', [
                'repair_order_id' => $repairOrder->id,
                'reason' => $reason,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function isBroadcastTransportFailure(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'pusher error')
            || str_contains($message, 'broadcast');
    }
}
