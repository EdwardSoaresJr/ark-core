<?php

namespace App\Ark\Operations\Conversations;

enum ConversationContactSurface: string
{
    case Phone = 'phone';
    case Email = 'email';
    case Website = 'website';
    case Messenger = 'messenger';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::Website => 'Website',
            self::Messenger => 'Messenger',
        };
    }
}
