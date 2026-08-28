<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Messaging\OutboundDeliveryMode;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Creates scheduled intent only. Does not send. Jobs own execution.
 */
final class ScheduleOutboundEstimateAction
{
    public function __construct(
        private readonly CancelScheduledOutboundMessagesAction $cancelPending,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        OutboundDeliveryMode $mode,
        ?string $recipientEmail = null,
        ?string $staffNote = null,
        bool $acknowledgeMissingVin = false,
        ?Conversation $conversation = null,
        ?CarbonImmutable $scheduledFor = null,
        bool $acknowledgeTimingFluids = false,
    ): ScheduledOutboundMessage {
        $repairOrder->loadMissing('customer');
        $repairOrder->ensureEstimateSendAllowed($acknowledgeMissingVin, $acknowledgeTimingFluids);

        if ($repairOrder->customer === null) {
            throw new RuntimeException('Repair order does not have a customer.');
        }

        if ($repairOrder->lines()->doesntExist()) {
            throw new RuntimeException('Add at least one estimate line before scheduling the estimate.');
        }

        $recipientPhone = null;
        $email = null;

        if ($mode->includesSms()) {
            $recipientPhone = PhoneNumber::normalize($repairOrder->customer->phone);

            if ($recipientPhone === null || $recipientPhone === '') {
                throw new RuntimeException('Customer does not have a phone number on file.');
            }
        }

        if ($mode->includesEmail()) {
            $email = strtolower(trim($recipientEmail ?? $repairOrder->customer->email ?? ''));

            if ($email === '') {
                throw new RuntimeException('Add a customer email on file or enter one to schedule the estimate.');
            }
        }

        $this->cancelPending->forRepairOrderEstimate(
            $repairOrder,
            $actor,
            reason: 'replaced',
        );

        $requestedAt = now('UTC');
        $scheduledFor ??= TomorrowMorningSchedule::nextInstant();

        if (! TomorrowMorningSchedule::isAllowedOpenMorning($scheduledFor)) {
            throw new RuntimeException('Choose a morning when the shop is open.');
        }

        $row = ScheduledOutboundMessage::query()->create([
            'type' => ScheduledOutboundMessageType::EstimateSend,
            'status' => ScheduledOutboundMessageStatus::Scheduled,
            'scheduled_for' => $scheduledFor,
            'repair_order_id' => $repairOrder->id,
            'conversation_id' => $conversation?->id,
            'requested_by_user_id' => $actor->id,
            'delivery_mode' => $mode,
            'recipient_phone' => $recipientPhone,
            'recipient_email' => $email,
            'payload_json' => array_filter([
                'staff_note' => filled($staffNote) ? trim($staffNote) : null,
                'acknowledge_missing_vin' => $acknowledgeMissingVin ?: null,
                'acknowledge_timing_fluids' => $acknowledgeTimingFluids ?: null,
            ], fn (mixed $value): bool => $value !== null),
            'requested_at' => $requestedAt,
        ]);

        DispatchScheduledOutboundEstimateJob::dispatch($row->id)
            ->delay($scheduledFor);

        return $row;
    }
}
