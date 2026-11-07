<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationMessage;

enum CommunicationsSurfaceChannel: string
{
    case All = 'all';
    case Phone = 'phone';
    case Sms = 'sms';
    case Email = 'email';
    case Messenger = 'messenger';
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Phone => 'Phone',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::Messenger => 'Messenger',
            self::Portal => 'Portal',
        };
    }

    /** Label for the inbox channel filter ("All channels" instead of "All"). */
    public function filterLabel(): string
    {
        return $this === self::All ? 'All channels' : $this->label();
    }

    public function icon(): string
    {
        return match ($this) {
            self::All => 'messages',
            self::Phone => 'phone',
            self::Sms => 'message',
            self::Email => 'mail',
            self::Messenger => 'brand-messenger',
            self::Portal => 'world',
        };
    }

    /**
     * The channel a message counts under in the queue and the inbox; null for channels without a filter
     * (in person, internal).
     */
    public static function forMessage(?ConversationMessage $message): ?self
    {
        if ($message === null) {
            return null;
        }

        if (($message->metadata['portal_estimate_view'] ?? false) === true) {
            return self::Portal;
        }

        return match ($message->channel) {
            OperationalCommunicationChannel::Messenger => self::Messenger,
            OperationalCommunicationChannel::Email => self::Email,
            OperationalCommunicationChannel::Sms => self::Sms,
            OperationalCommunicationChannel::Website => self::Portal,
            OperationalCommunicationChannel::Phone => self::Phone,
            default => null,
        };
    }

    public static function fromQuery(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::All;
        }

        return self::tryFrom($value) ?? self::All;
    }

    /**
     * @return list<self>
     */
    public static function filterTabs(): array
    {
        return [
            self::All,
            self::Phone,
            self::Sms,
            self::Messenger,
            self::Email,
            self::Portal,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function matchesRow(array $row): bool
    {
        if ($this === self::All) {
            return true;
        }

        return (string) ($row['queue_tab'] ?? '') === $this->value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function tabForRow(array $row): self
    {
        return self::tryFrom((string) ($row['queue_tab'] ?? '')) ?? self::Phone;
    }
}