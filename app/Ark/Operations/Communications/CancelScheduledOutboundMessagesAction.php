<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

final class CancelScheduledOutboundMessagesAction
{
    public function forRepairOrderEstimate(
        RepairOrder $repairOrder,
        ?User $actor = null,
        string $reason = 'cancelled',
    ): int {
        $pending = ScheduledOutboundMessage::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('type', ScheduledOutboundMessageType::EstimateSend)
            ->where('status', ScheduledOutboundMessageStatus::Scheduled)
            ->get();

        foreach ($pending as $row) {
            $this->cancel($row, $actor, $reason);
        }

        return $pending->count();
    }

    public function forCustomerSmsReply(
        int $customerId,
        ?User $actor = null,
        string $reason = 'cancelled',
    ): int {
        $pending = ScheduledOutboundMessage::query()
            ->where('customer_id', $customerId)
            ->where('type', ScheduledOutboundMessageType::SmsReply)
            ->where('status', ScheduledOutboundMessageStatus::Scheduled)
            ->get();

        foreach ($pending as $row) {
            $this->cancel($row, $actor, $reason);
        }

        return $pending->count();
    }

    public function cancel(
        ScheduledOutboundMessage $row,
        ?User $actor = null,
        string $reason = 'cancelled',
    ): void {
        if (! $row->isScheduled()) {
            return;
        }

        $row->forceFill([
            'status' => ScheduledOutboundMessageStatus::Cancelled,
            'cancelled_at' => now('UTC'),
            'cancelled_by_user_id' => $actor?->id,
            'failure_reason' => $reason === 'cancelled' ? null : $reason,
        ])->save();
    }
}
