<?php

namespace App\Ark\Operations\Conversations;

enum ConversationStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Archived => 'Archived',
        };
    }
}
