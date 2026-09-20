<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\PhoneNumber;

final class IngressCreateContactUrl
{
    public static function forLead(Lead $lead): ?string
    {
        if ($lead->customer_id !== null || ! $lead->isOpen() || blank($lead->contact_phone)) {
            return null;
        }

        return route('operations.leads.create-contact', $lead);
    }

    public static function forPhone(
        string $phone,
        ?int $conversationId = null,
        ?int $callSessionId = null,
    ): ?string {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        return route('operations.ingress.create-contact', array_filter([
            'phone' => $normalized,
            'conversation_id' => $conversationId,
            'call_session_id' => $callSessionId,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }
}
