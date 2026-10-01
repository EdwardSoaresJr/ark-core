<?php

namespace App\Ark\Operations\Encounters;

enum EncounterSource: string
{
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Website = 'website';
    case Sms = 'sms';
    case Email = 'email';
    case RepairPal = 'repairpal';
    case Tow = 'tow';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
            self::Website => 'Website',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::RepairPal => 'RepairPal',
            self::Tow => 'Tow',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $source): array => [$source->value => $source->label()])
            ->all();
    }
}
