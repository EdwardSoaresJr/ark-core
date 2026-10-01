<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

class RecordCustomerSmsConsentAction
{
    public function __construct(
        private readonly CommunicationEventRecorder $communicationEvents,
    ) {}

    public function optOut(Customer $customer, ?string $providerMessageSid = null): void
    {
        $this->applyConsentChange(
            customer: $customer,
            status: CustomerSmsConsentStatus::OptedOut,
            eventType: OperationalCommunicationType::SmsOptOut,
            summary: 'Customer opted out of SMS via STOP keyword.',
            providerMessageSid: $providerMessageSid,
        );
    }

    public function optIn(Customer $customer, ?string $providerMessageSid = null): void
    {
        $this->applyConsentChange(
            customer: $customer,
            status: CustomerSmsConsentStatus::Subscribed,
            eventType: OperationalCommunicationType::SmsOptIn,
            summary: 'Customer re-subscribed to SMS via START keyword.',
            providerMessageSid: $providerMessageSid,
        );
    }

    public function confirmationMessage(CustomerSmsConsentStatus $status): string
    {
        $shopName = trim((string) ShopSettings::current()->shop_name) ?: 'the shop';

        return match ($status) {
            CustomerSmsConsentStatus::OptedOut => 'You have been unsubscribed from text messages. Reply START to resubscribe.',
            CustomerSmsConsentStatus::Subscribed => "You are now subscribed to text messages from {$shopName}. Reply STOP to unsubscribe.",
        };
    }

    private function applyConsentChange(
        Customer $customer,
        CustomerSmsConsentStatus $status,
        OperationalCommunicationType $eventType,
        string $summary,
        ?string $providerMessageSid,
    ): void {
        if ($customer->sms_consent_status === $status) {
            return;
        }

        $occurredAt = now();

        $customer->forceFill([
            'sms_consent_status' => $status,
            'sms_consent_at' => $occurredAt,
        ])->save();

        $repairOrder = $this->latestRepairOrder($customer);

        if ($repairOrder === null) {
            return;
        }

        if ($providerMessageSid !== null && $providerMessageSid !== '') {
            $summary .= ' (Twilio '.$providerMessageSid.')';
        }

        $this->communicationEvents->record(
            $repairOrder,
            $eventType,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Inbound,
            $summary,
            occurredAt: $occurredAt instanceof Carbon ? $occurredAt : now(),
        );
    }

    private function latestRepairOrder(Customer $customer): ?RepairOrder
    {
        return $customer->repairOrders()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }
}
