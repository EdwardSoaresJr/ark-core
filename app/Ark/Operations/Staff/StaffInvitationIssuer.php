<?php

namespace App\Ark\Operations\Staff;

use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class StaffInvitationIssuer
{
    public const INVITE_VALID_DAYS = 7;

    public function send(User $user): void
    {
        if (! $user->isActive()) {
            throw new RuntimeException('Cannot invite a disabled staff account.');
        }

        $user->notify(new StaffInvitationNotification);
    }

    public static function placeholderPassword(): string
    {
        return Hash::make(Str::password(64));
    }
}
