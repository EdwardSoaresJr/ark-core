<?php

namespace App\Ark\Operations\Today\Lifecycle;

enum TodayCompletionAuthority: string
{
    case Communication = 'communication';
    case Inventory = 'inventory';
    case Content = 'content';
    case CheckIn = 'check_in';
    case Financial = 'financial';

    public function label(): string
    {
        return match ($this) {
            self::Communication => 'Communication',
            self::Inventory => 'Inventory',
            self::Content => 'Content',
            self::CheckIn => 'Check-in',
            self::Financial => 'Financial',
        };
    }
}
