<?php

namespace App\Ark\Operations\Parts;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class UserPartsTechCredentials
{
    public static function apply(User $user, ?string $username, ?string $password): void
    {
        $username = trim((string) ($username ?? ''));

        if ($username === '') {
            $user->forceFill([
                'partstech_username' => null,
                'partstech_password' => null,
            ]);

            return;
        }

        $user->forceFill(['partstech_username' => $username]);

        $password = trim((string) ($password ?? ''));

        if ($password !== '') {
            $user->forceFill(['partstech_password' => $password]);
        }
    }

    public static function guardPasswordRequired(User $user, ?string $username, ?string $password, string $redirectTo): void
    {
        $username = trim((string) ($username ?? ''));
        $password = trim((string) ($password ?? ''));

        if ($username === '' || $password !== '' || $user->hasStoredPartsTechPassword()) {
            return;
        }

        throw ValidationException::withMessages([
            'partstech_password' => 'Enter your PartsTech password when adding a personal PartsTech username.',
        ])->redirectTo($redirectTo);
    }
}
