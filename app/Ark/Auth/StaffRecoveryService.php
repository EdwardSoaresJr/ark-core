<?php

namespace App\Ark\Auth;

use App\Ark\Platform\EssentialDeliveryClient;
use App\Ark\Install\RecoveryOwnerIdentity;
use App\Models\StaffRecoveryChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class StaffRecoveryService
{
    public function __construct(
        private readonly EssentialDeliveryClient $delivery,
    ) {}

    /**
     * Always returns a generic success message to avoid account enumeration.
     */
    public function requestCode(string $email): string
    {
        $email = strtolower(trim($email));
        $recoveryOwner = RecoveryOwnerIdentity::email();

        if ($recoveryOwner === null || $email !== $recoveryOwner) {
            return $this->genericSentMessage();
        }

        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        if ($user === null) {
            return $this->genericSentMessage();
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $publicId = (string) Str::uuid();
        $idempotencyKey = 'recovery-'.$publicId;

        StaffRecoveryChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $challenge = StaffRecoveryChallenge::query()->create([
            'public_id' => $publicId,
            'user_id' => $user->id,
            'code_hash' => StaffRecoveryChallenge::hashCode($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        $result = $this->delivery->deliverPasswordRecoveryCode(
            $challenge->public_id,
            $code,
            $idempotencyKey,
        );

        if (! ($result['ok'] ?? false)) {
            $challenge->delete();

            return 'Could not send a recovery code right now. Try again shortly or contact your shop administrator.';
        }

        return $this->genericSentMessage();
    }

    public function resetPassword(string $email, string $code, string $password): void
    {
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->where('is_active', true)->firstOrFail();

        $challenge = StaffRecoveryChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->orderByDesc('id')
            ->first();

        if ($challenge === null || ! $challenge->isUsable()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => 'This recovery code is invalid or expired.',
            ]);
        }

        if ($challenge->attempts >= 5) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => 'Too many attempts. Request a new code.',
            ]);
        }

        if (! hash_equals($challenge->code_hash, StaffRecoveryChallenge::hashCode($code))) {
            $challenge->increment('attempts');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => 'This recovery code is invalid or expired.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'password_set_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $challenge->forceFill(['used_at' => now()])->save();
    }

    private function genericSentMessage(): string
    {
        return 'If this account can receive recovery mail, a code was sent to the recovery owner email on file.';
    }
}
