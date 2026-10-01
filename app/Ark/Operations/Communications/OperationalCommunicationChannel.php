<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationContactSurface;

enum OperationalCommunicationChannel: string
{
    case Phone = 'phone';
    case Sms = 'sms';
    case Email = 'email';
    case Messenger = 'messenger';
    case InPerson = 'in_person';
    case Internal = 'internal';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::Messenger => 'Messenger',
            self::InPerson => 'In person',
            self::Internal => 'Internal',
            self::Website => 'Website',
        };
    }

    public function inboundQueueContactSurface(): ?ConversationContactSurface
    {
        return match ($this) {
            self::Sms => ConversationContactSurface::Phone,
            self::Messenger => ConversationContactSurface::Messenger,
            default => null,
        };
    }

    public function supportsInboundQueue(): bool
    {
        return $this->inboundQueueContactSurface() !== null;
    }

    /**
     * @return list<self>
     */
    public static function inboundQueueCandidates(): array
    {
        return [
            self::Sms,
            self::Messenger,
        ];
    }
}
