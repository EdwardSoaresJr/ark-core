<?php

namespace App\Ark\Operations\Communications;

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