<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;

final class InboundCallerDisplayPhone
{
    public function forSession(CallSession $session): string
    {
        if ($session->direction !== CallSessionDirection::Inbound) {
            return $this->displayOrRaw($session->normalized_to)
                ?? $this->displayOrRaw($session->to_number)
                ?? 'Unknown';
        }

        $shopNormalized = $this->shopNormalizedNumber();
        $fromNormalized = PhoneNumber::normalize($session->normalized_from)
            ?? PhoneNumber::normalize($session->from_number);

        if ($fromNormalized !== null && $fromNormalized !== $shopNormalized) {
            return $this->displayOrRaw($fromNormalized) ?? $session->from_number;
        }

        $state = app(TelephonyRingState::class)->get($session->provider_call_sid);
        $fromRingState = PhoneNumber::normalize($state['customer_caller_id'] ?? null);

        if ($fromRingState !== null && $fromRingState !== $shopNormalized) {
            return $this->displayOrRaw($fromRingState) ?? (string) $state['customer_caller_id'];
        }

        if ($fromNormalized !== null) {
            return $this->displayOrRaw($fromNormalized) ?? $session->from_number;
        }

        return PhoneNumber::display($session->from_number)
            ?? $session->from_number
            ?: 'Unknown';
    }

    public function normalizedForSession(CallSession $session): ?string
    {
        if ($session->direction !== CallSessionDirection::Inbound) {
            return PhoneNumber::normalize($session->normalized_to)
                ?? PhoneNumber::normalize($session->to_number);
        }

        $shopNormalized = $this->shopNormalizedNumber();
        $fromNormalized = PhoneNumber::normalize($session->normalized_from)
            ?? PhoneNumber::normalize($session->from_number);

        if ($fromNormalized !== null && $fromNormalized !== $shopNormalized) {
            return $fromNormalized;
        }

        $state = app(TelephonyRingState::class)->get($session->provider_call_sid);

        return PhoneNumber::normalize($state['customer_caller_id'] ?? null) ?? $fromNormalized;
    }

    private function shopNormalizedNumber(): ?string
    {
        return PhoneNumber::normalize(ShopSettings::current()->telephony_inbound_number);
    }

    private function displayOrRaw(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        return PhoneNumber::display($phone) ?? $phone;
    }
}
