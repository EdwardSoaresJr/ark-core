<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\EmailVerification\EmailVerification;
use App\Ark\Operations\EmailVerification\EmailVerificationAuthority;
use Illuminate\Contracts\Session\Session;

/**
 * Book adapter over Email Verification Authority.
 */
final class LeadEmailVerification
{
    public const SESSION_KEY = EmailVerificationAuthority::SESSION_KEY;

    public function __construct(
        private readonly EmailVerificationAuthority $authority,
    ) {}

    public function ready(): bool
    {
        return $this->authority->ready();
    }

    public function verifiedEmail(Session $session): ?string
    {
        return $this->authority->verifiedEmail($session);
    }

    public function consumeBookProof(Session $session, ?string $email = null): bool
    {
        return $this->authority->consumeVerifiedSession($session, $email);
    }

    public function markVerified(Session $session, string $email): void
    {
        $normalized = EmailVerification::normalizeEmail($email);

        if ($normalized === null) {
            return;
        }

        $session->put(EmailVerificationAuthority::SESSION_KEY, [
            'email' => $normalized,
            'verified_at' => now()->timestamp,
        ]);
    }
}
