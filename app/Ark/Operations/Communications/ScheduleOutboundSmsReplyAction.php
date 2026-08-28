<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\OutboundDeliveryMode;
use App\Ark\Operations\Messaging\ResolvePhoneSmsCapabilityAction;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Creates scheduled SMS reply intent only. Does not send. Jobs own execution.
 */
final class ScheduleOutboundSmsReplyAction
{
    public function __construct(
        private readonly CancelScheduledOutboundMessagesAction $cancelPending,
        private readonly ShopIntegrationCredentials $credentials,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
    ) {}

    public function execute(
        Customer $customer,
        User $actor,
        string $body,
        ?RepairOrder $repairOrder = null,
        ?Conversation $conversation = null,
        ?CarbonImmutable $scheduledFor = null,
    ): ScheduledOutboundMessage {
        $messageBody = trim($body);

        if ($messageBody === '') {
            throw new RuntimeException('Enter a message to schedule.');
        }

        if (mb_strlen($messageBody) > 1600) {
            throw new RuntimeException('Message is too long to schedule.');
        }

        if (! $this->credentials->twilioConfigured()) {
            throw new RuntimeException('Shop messaging is disabled.');
        }

        $eligibility = CustomerSmsSendEligibility::for($customer, $this->credentials);

        if ($blockReason = $eligibility->blockReason()) {
            throw new RuntimeException($blockReason);
        }

        $recipientPhone = PhoneNumber::normalize($customer->phone);

        if ($recipientPhone === null || $recipientPhone === '') {
            throw new RuntimeException('Customer does not have a phone number on file.');
        }

        $this->smsCapability->assertCapableOrFail($recipientPhone);

        $this->cancelPending->forCustomerSmsReply(
            $customer->id,
            $actor,
            reason: 'replaced',
        );

        $requestedAt = now('UTC');
        $scheduledFor ??= TomorrowMorningSchedule::nextInstant();

        if (! TomorrowMorningSchedule::isAllowedOpenMorning($scheduledFor)) {
            throw new RuntimeException('Choose a morning when the shop is open.');
        }

        $row = ScheduledOutboundMessage::query()->create([
            'type' => ScheduledOutboundMessageType::SmsReply,
            'status' => ScheduledOutboundMessageStatus::Scheduled,
            'scheduled_for' => $scheduledFor,
            'repair_order_id' => $repairOrder?->id,
            'customer_id' => $customer->id,
            'conversation_id' => $conversation?->id,
            'requested_by_user_id' => $actor->id,
            'delivery_mode' => OutboundDeliveryMode::Sms,
            'recipient_phone' => $recipientPhone,
            'recipient_email' => null,
            'payload_json' => [
                'body' => $messageBody,
            ],
            'requested_at' => $requestedAt,
        ]);

        DispatchScheduledOutboundSmsReplyJob::dispatch($row->id)
            ->delay($scheduledFor);

        return $row;
    }
}
