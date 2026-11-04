<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;

/**
 * Core's own answers: the delivery status a provider reported on the message (metadata delivery_status and
 * delivery_error_code), and the customer's SMS consent (STOP and START).
 */
final class CoreCommunicationsWorkspaceSignals implements CommunicationsWorkspaceSignals
{
    public function deliveries(iterable $messages): array
    {
        $deliveries = [];
        foreach ($messages as $message) {
            $status = strtolower(trim((string) (($message->metadata ?? [])['delivery_status'] ?? '')));
            if ($status === '' || $message->direction !== OperationalCommunicationDirection::Outbound) {
                continue;
            }
            $error = trim((string) (($message->metadata ?? [])['delivery_error_code'] ?? ''));
            $deliveries[(int) $message->id] = ['status' => $status, 'error_code' => $error !== '' ? $error : null];
        }

        return $deliveries;
    }

    public function contactPermission(Conversation $conversation, ?Customer $customer): ?array
    {
        return match ($customer?->sms_consent_status) {
            CustomerSmsConsentStatus::OptedOut => ['texting' => 'opted_out', 'source' => 'Replied STOP'],
            CustomerSmsConsentStatus::Subscribed => ['texting' => 'allowed', 'source' => null],
            default => null,
        };
    }

    public function signature(?int $conversationId): string
    {
        return '';
    }

    public function liveEvents(): array
    {
        return [];
    }
}
