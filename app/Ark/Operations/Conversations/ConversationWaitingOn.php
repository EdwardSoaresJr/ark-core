<?php

namespace App\Ark\Operations\Conversations;

enum ConversationWaitingOn: string
{
    case Shop = 'shop';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Shop => 'Needs shop',
            self::Customer => 'Waiting on customer',
        };
    }
}
