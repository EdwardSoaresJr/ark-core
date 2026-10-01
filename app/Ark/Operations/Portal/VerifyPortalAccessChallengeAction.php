<?php

namespace App\Ark\Operations\Portal;

final class VerifyPortalAccessChallengeAction
{
    private const MAX_ATTEMPTS = 5;

    public function execute(PortalAccessChallenge $challenge, string $plainCode): bool
    {
        if (! $challenge->isUsable()) {
            return false;
        }

        $plainCode = trim($plainCode);

        if (! preg_match('/^\d{6}$/', $plainCode)) {
            $challenge->increment('attempts');

            return false;
        }

        if (! hash_equals($challenge->code_hash, PortalAccessChallenge::hashCode($plainCode))) {
            $challenge->increment('attempts');

            return false;
        }

        $challenge->forceFill(['used_at' => now()])->save();

        return true;
    }

    public function maxAttempts(): int
    {
        return self::MAX_ATTEMPTS;
    }
}
