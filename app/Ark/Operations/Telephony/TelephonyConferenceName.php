<?php

namespace App\Ark\Operations\Telephony;

final class TelephonyConferenceName
{
    public static function forParentCall(string $parentCallSid): string
    {
        $safeSid = preg_replace('/[^A-Za-z0-9]/', '', $parentCallSid) ?? $parentCallSid;

        return 'ark-ring-'.$safeSid;
    }
}
