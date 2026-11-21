<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;

/**
 * Core's own answers: the delivery status a provider reported on the message (metadata delivery_status and
 * delivery_error_code), the customer's SMS consent (STOP and START), destination-number capability, and whether
 * outbound texting is configured for this shop.
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
        $destination = $conversation->contact_surface === ConversationContactSurface::Phone
            ? PhoneNumber::normalize((string) $conversation->contact_address)
            : PhoneNumber::normalize((string) ($customer?->phone ?? ''));

        if ($customer?->sms_consent_status === CustomerSmsConsentStatus::OptedOut) {
            return ['texting' => 'opted_out', 'source' => 'Replied STOP'];
        }

        if ($destination !== null) {
            $capability = PhoneSmsCapability::findByNormalizedPhone($destination);
            if ($capability !== null && ! $capability->sms_capable) {
                $landline = str_contains(strtolower((string) ($capability->line_type ?? '')), 'landline')
                    || str_contains(strtolower((string) ($capability->reason ?? '')), 'landline');

                return [
                    'texting' => 'unavailable',
                    'unavailable_reason' => $landline ? 'landline' : 'unsupported',
                    'source' => $capability->blockReason() ?? 'Texting unavailable for this number',
                    'notes' => array_values(array_filter([
                        $capability->blockReason() ?? 'This number cannot receive texts.',
                    ])),
                ];
            }
        }

        if (! $this->textingConfigured()) {
            // Standalone Core with no provider: do not invent a permission badge; the composer already explains setup.
            return match ($customer?->sms_consent_status) {
                CustomerSmsConsentStatus::Subscribed => null,
                default => null,
            };
        }

        return match ($customer?->sms_consent_status) {
            CustomerSmsConsentStatus::Subscribed => ['texting' => 'allowed', 'source' => null],
            default => null,
        };
    }

    public function messageNotes(iterable $messages): array
    {
        return [];
    }

    public function notices(): array
    {
        return [];
    }

    public function emailReplyUrl(Conversation $conversation): ?string
    {
        return null;
    }

    public function signature(?int $conversationId): string
    {
        return '';
    }

    public function liveEvents(): array
    {
        return [];
    }

    private function textingConfigured(): bool
    {
        if (ManagedCommunicationsGate::platformSend()) {
            return true;
        }

        try {
            return app(OutboundSmsTransport::class)->isConfigured();
        } catch (\Throwable) {
            return false;
        }
    }
}
