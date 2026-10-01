<?php

namespace App\Ark\Operations\Messaging;

final class ConversationBroadcast
{
    public static function enabled(): bool
    {
        $connection = config('broadcasting.default');

        return filled($connection) && $connection !== 'null';
    }

    public static function channelName(): string
    {
        return 'operations.conversations';
    }
}
