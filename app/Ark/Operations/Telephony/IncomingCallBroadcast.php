<?php

namespace App\Ark\Operations\Telephony;

final class IncomingCallBroadcast
{
    public static function enabled(): bool
    {
        $connection = config('broadcasting.default');

        return filled($connection) && $connection !== 'null';
    }

    public static function channelName(): string
    {
        return 'operations.incoming-calls';
    }
}
