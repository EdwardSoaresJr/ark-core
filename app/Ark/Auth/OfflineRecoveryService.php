<?php

namespace App\Ark\Auth;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Install\RecoveryOwnerIdentity;
use App\Models\OfflineRecoveryChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OfflineRecoveryService
{
    public function __construct(
        private readonly OfflineRecoveryVerifier $verifier,
    ) {}

    public function issueChallenge(): OfflineRecoveryChallenge
    {
        OfflineRecoveryChallenge::query()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        return OfflineRecoveryChallenge::query()->create([
            'public_id' => (string) Str::uuid(),
            'installation_uuid' => InstallationIdentity::uuid(),
            'expires_at' => now()->addMinutes(20),
        ]);
    }

    public function resetPassword(string $authorization, string $password): void
    {
        $claims = $this->verifier->verify($authorization);
        $installationUuid = InstallationIdentity::uuid();

        if (! hash_equals($installationUuid, $claims['installation_uuid'])) {
            throw ValidationException::withMessages([
                'authorization' => 'This authorization is not for this Box.',
            ]);
        }

        $challenge = OfflineRecoveryChallenge::query()
            ->where('public_id', $claims['challenge'])
            ->where('installation_uuid', $installationUuid)
            ->first();

        if ($challenge === null || ! $challenge->isUsable()) {
            throw ValidationException::withMessages([
                'authorization' => 'This challenge is invalid, expired, or already used.',
            ]);
        }

        if (OfflineRecoveryChallenge::query()->where('consumed_jti', $claims['jti'])->exists()) {
            throw ValidationException::withMessages([
                'authorization' => 'This authorization has already been used.',
            ]);
        }

        $email = RecoveryOwnerIdentity::email();
        $user = $email === null
            ? null
            : User::query()->where('email', $email)->where('is_active', true)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'authorization' => 'Recovery owner is not available on this Box.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'password_set_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $challenge->forceFill([
            'consumed_at' => now(),
            'consumed_jti' => $claims['jti'],
        ])->save();
    }
}
