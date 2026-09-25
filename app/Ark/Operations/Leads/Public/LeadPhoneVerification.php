<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use Illuminate\Contracts\Session\Session;

final class LeadPhoneVerification
{
    public function __construct(
        private readonly PhoneVerificationAuthority $authority,
    ) {}

    public function required(): bool
    {
        return $this->authority->ready();
    }

    public function bookIdentityGateReady(): bool
    {
        return $this->authority->ready();
    }

    public function verifiedPhone(Session $session): ?string
    {
        return $this->authority->verifiedPhone($session);
    }

    public function consumeBookProof(Session $session, string $phone): bool
    {
        return $this->authority->consumeVerifiedSession($session, $phone);
    }

    public function markVerified(Session $session, string $phone): void
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return;
        }

        $session->put(PhoneVerificationAuthority::SESSION_KEY, [
            'phone' => $normalized,
            'verified_at' => now()->timestamp,
        ]);
    }
}
