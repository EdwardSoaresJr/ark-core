<?php

namespace App\Ark\Operations\Conversations;

enum ConversationParticipantType: string
{
    case Customer = 'customer';
    case Advisor = 'advisor';
    case AiAgent = 'ai_agent';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Advisor => 'Advisor',
            self::AiAgent => 'AI Agent',
            self::System => 'System',
        };
    }
}
