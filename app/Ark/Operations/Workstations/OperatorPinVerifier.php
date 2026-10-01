<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class OperatorPinVerifier
{
    public function verify(User $user, string $pin): bool
    {
        $normalized = $this->normalize($pin);

        if ($normalized === null || ! filled($user->operator_pin_hash)) {
            return false;
        }

        return Hash::check($normalized, (string) $user->operator_pin_hash);
    }

    public function assertValid(User $user, string $pin): void
    {
        if (! filled($user->operator_pin_hash)) {
            throw ValidationException::withMessages([
                'pin' => 'This staff member does not have a workstation PIN yet.',
            ]);
        }

        if (! $this->verify($user, $pin)) {
            throw ValidationException::withMessages([
                'pin' => 'Incorrect PIN.',
            ]);
        }
    }

    public function normalize(?string $pin): ?string
    {
        if ($pin === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $pin);

        if (! is_string($digits) || strlen($digits) !== 4) {
            return null;
        }

        return $digits;
    }

    public function hash(string $pin): string
    {
        $normalized = $this->normalize($pin);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'operator_pin' => 'Workstation PIN must be exactly 4 digits.',
            ]);
        }

        return Hash::make($normalized);
    }
}
