<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use Illuminate\Contracts\Session\Session;

/**
 * Lead / Book adapter over Phone Verification Authority.
 * Presentation consumers keep this type; authority owns codes and sessions.
 */
final class LeadPhoneVerification
{
    /** @deprecated Use PhoneVerificationAuthority::SESSION_KEY — kept for session continuity. */
    public const SESSION_KEY = PhoneVerificationAuthority::SESSION_KEY;

    public function __construct(
        private readonly PhoneVerificationAuthority $authority,
    ) {}

    public function required(): bool
    {
        if (! config('public_lead.phone_verification_required', true)) {
            return false;
        }

        return $this->authority->ready();
    }

    public function markVerified(Session $session, string $phone): void
    {
        // Prefer authority verify(); this remains for rare direct session seeds in tests.
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return;
        }

        $session->put(PhoneVerificationAuthority::SESSION_KEY, [
            'phone' => $normalized,
            'verified_at' => now()->timestamp,
        ]);
    }

    public function isVerified(Session $session, string $phone): bool
    {
        if (! $this->required()) {
            return true;
        }

        $normalized = PhoneNumber::normalize($phone);
        $proof = $this->authority->verifiedPhone($session);

        return $normalized !== null && $proof !== null && $proof === $normalized;
    }

    public function consume(Session $session, string $phone): bool
    {
        if (! $this->required()) {
            return true;
        }

        return $this->authority->consumeVerifiedSession($session, $phone);
    }

    public function verifiedPhone(Session $session): ?string
    {
        return $this->authority->verifiedPhone($session);
    }

    public function bookIdentityGateReady(): bool
    {
        return $this->authority->ready();
    }

    public function consumeBookProof(Session $session, string $phone): bool
    {
        return $this->authority->consumeVerifiedSession($session, $phone);
    }
}
