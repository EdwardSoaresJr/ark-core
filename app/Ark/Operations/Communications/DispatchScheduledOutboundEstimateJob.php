<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Messaging\SendEstimateDeliveryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Owns execution only. The scheduled row never sends.
 */
class DispatchScheduledOutboundEstimateJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $scheduledOutboundMessageId,
    ) {}

    public function handle(
        SendEstimateDeliveryAction $send,
        CancelScheduledOutboundMessagesAction $cancel,
    ): void {
        $row = ScheduledOutboundMessage::query()->find($this->scheduledOutboundMessageId);

        if ($row === null || ! $row->isScheduled()) {
            return;
        }

        $repairOrder = RepairOrder::query()->with('customer')->find($row->repair_order_id);

        if ($repairOrder === null) {
            $cancel->cancel($row, reason: 'repair_order_missing');

            return;
        }

        if ($this->estimateAlreadySentAfterRequest($repairOrder, $row)) {
            $cancel->cancel($row, reason: 'superseded');

            return;
        }

        $actor = User::query()->find($row->requested_by_user_id);

        if ($actor === null) {
            $row->forceFill([
                'status' => ScheduledOutboundMessageStatus::Failed,
                'failed_at' => now('UTC'),
                'failure_reason' => 'Requesting advisor no longer exists.',
            ])->save();

            return;
        }

        $payload = is_array($row->payload_json) ? $row->payload_json : [];
        $acknowledgeMissingVin = (bool) ($payload['acknowledge_missing_vin'] ?? false);
        $acknowledgeTimingFluids = (bool) ($payload['acknowledge_timing_fluids'] ?? false);
        $staffNote = isset($payload['staff_note']) ? (string) $payload['staff_note'] : null;
        $conversation = $row->conversation_id !== null
            ? $row->conversation()->first()
            : null;

        try {
            $send->execute(
                $repairOrder,
                $actor,
                $row->delivery_mode,
                $row->recipient_email,
                $staffNote,
                $acknowledgeMissingVin,
                recipientPhone: $row->recipient_phone,
                conversation: $conversation,
                acknowledgeTimingFluids: $acknowledgeTimingFluids,
            );

            $row->forceFill([
                'status' => ScheduledOutboundMessageStatus::Sent,
                'sent_at' => now('UTC'),
            ])->save();
        } catch (Throwable $exception) {
            $row->forceFill([
                'status' => ScheduledOutboundMessageStatus::Failed,
                'failed_at' => now('UTC'),
                'failure_reason' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Scheduled estimate send failed.',
            ])->save();
        }
    }

    private function estimateAlreadySentAfterRequest(RepairOrder $repairOrder, ScheduledOutboundMessage $row): bool
    {
        return CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', OperationalCommunicationType::EstimateSent)
            ->where('created_at', '>', $row->requested_at)
            ->exists();
    }
}
