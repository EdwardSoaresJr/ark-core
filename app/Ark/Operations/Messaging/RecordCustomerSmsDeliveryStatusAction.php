<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Carbon;

class RecordCustomerSmsDeliveryStatusAction
{
    public function __construct(
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
    ) {}

    public function execute(Customer $customer, string $messageSid, string $status, ?string $errorCode = null): void
    {
        $messageSid = trim($messageSid);
        $status = strtolower(trim($status));

        if ($messageSid === '' || $status === '') {
            return;
        }

        $occurredAt = now();
        $errorCode = trim((string) $errorCode);

        $this->applyCustomerState($customer, $status, $errorCode, $occurredAt);

        if (in_array($status, ['failed', 'undelivered'], true) && filled($customer->phone)) {
            $this->smsCapability->markNotCapableFromDeliveryFailure(
                (string) $customer->phone,
                $errorCode !== '' ? $errorCode : null,
            );
        }

        if (! in_array($status, ['delivered', 'failed', 'undelivered'], true)) {
            return;
        }

        $message = $this->findOutboundMessage($messageSid);
        $repairOrder = $this->resolveRepairOrder($customer, $message);

        if ($repairOrder === null) {
            return;
        }

        $eventType = $status === 'delivered'
            ? OperationalCommunicationType::SmsDelivered
            : OperationalCommunicationType::SmsDeliveryFailed;

        if ($this->deliveryFactAlreadyRecorded($repairOrder, $message, $messageSid, $eventType)) {
            return;
        }

        $summary = $status === 'delivered'
            ? 'SMS delivered to customer.'
            : 'SMS delivery failed'.($errorCode !== '' ? ' (Twilio '.$errorCode.')' : '.');

        $summary .= ' (Twilio '.$messageSid.')';

        $this->communicationEvents->record(
            $repairOrder,
            $eventType,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Outbound,
            $summary,
            message: $message,
            occurredAt: $occurredAt,
        );
    }

    private function applyCustomerState(
        Customer $customer,
        string $status,
        string $errorCode,
        Carbon $occurredAt,
    ): void {
        $updates = [
            'last_sms_delivery_status' => $status,
        ];

        if ($status === 'delivered') {
            if ($customer->last_sms_delivery_status !== 'delivered' || $customer->last_sms_delivered_at === null) {
                $updates['last_sms_delivered_at'] = $occurredAt;
            }

            $updates['last_sms_error_code'] = null;
        }

        if (in_array($status, ['failed', 'undelivered'], true)) {
            if (! in_array((string) $customer->last_sms_delivery_status, ['failed', 'undelivered'], true)
                || $customer->last_sms_failed_at === null) {
                $updates['last_sms_failed_at'] = $occurredAt;
            }

            if ($errorCode !== '') {
                $updates['last_sms_error_code'] = $errorCode;
            }
        }

        $customer->forceFill($updates)->save();
    }

    private function findOutboundMessage(string $messageSid): ?ConversationMessage
    {
        return ConversationMessage::query()
            ->where('metadata->twilio_message_sid', $messageSid)
            ->first();
    }

    private function resolveRepairOrder(Customer $customer, ?ConversationMessage $message): ?RepairOrder
    {
        $repairOrderId = $message?->metadata['repair_order_id'] ?? null;

        if (is_numeric($repairOrderId)) {
            return RepairOrder::query()->find((int) $repairOrderId);
        }

        return $customer->repairOrders()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    private function deliveryFactAlreadyRecorded(
        RepairOrder $repairOrder,
        ?ConversationMessage $message,
        string $messageSid,
        OperationalCommunicationType $eventType,
    ): bool {
        $query = CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', $eventType);

        if ($message !== null) {
            return $query
                ->where('conversation_message_id', $message->id)
                ->exists();
        }

        return $query
            ->where('summary', 'like', '%(Twilio '.$messageSid.')%')
            ->exists();
    }
}
