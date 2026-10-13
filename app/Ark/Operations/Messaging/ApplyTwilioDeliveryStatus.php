<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;

/**
 * Applies a Twilio delivery callback to the customer and repair-order history.
 * Conversation messages stay append-only; status is recorded beside them.
 */
final class ApplyTwilioDeliveryStatus
{
    public function __construct(private readonly RecordCustomerSmsDeliveryStatusAction $delivery) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function apply(array $params): bool
    {
        $sid = trim((string) ($params['MessageSid'] ?? ''));
        $status = strtolower(trim((string) ($params['MessageStatus'] ?? '')));
        if ($sid === '' || $status === '') {
            return false;
        }

        $customer = $this->customer($sid, (string) ($params['To'] ?? ''));
        if ($customer === null) {
            return false;
        }

        $error = trim((string) ($params['ErrorCode'] ?? ''));
        $this->delivery->execute($customer, $sid, $status, $error !== '' ? $error : null);

        return true;
    }

    private function customer(string $sid, string $to): ?Customer
    {
        $message = ConversationMessage::query()
            ->where('metadata->twilio_message_sid', $sid)
            ->first();
        if ($message !== null) {
            $participant = ConversationParticipant::query()
                ->where('conversation_id', $message->conversation_id)
                ->whereNotNull('customer_id')
                ->first();
            if ($participant?->customer !== null) {
                return $participant->customer;
            }
        }

        $normalized = PhoneNumber::normalize($to);
        if ($normalized === null) {
            return null;
        }

        return Customer::query()
            ->where('phone', $normalized)
            ->orWhere('phone', $to)
            ->first();
    }
}
