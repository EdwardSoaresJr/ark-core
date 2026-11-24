<?php

namespace App\Ark\Runtime\Preferences;

/**
 * Shared List | Cards presentation for record indexes (Customers, Vehicles, Repair Orders).
 * Job Board keeps its own JobBoardLayout enum until a later unify pass.
 */
enum CollectionLayout: string
{
    case List = 'list';
    case Cards = 'cards';

    public function label(): string
    {
        return match ($this) {
            self::List => 'List',
            self::Cards => 'Cards',
        };
    }

    public static function parse(?string $value): self
    {
        return match ($value) {
            self::Cards->value => self::Cards,
            default => self::List,
        };
    }

    public static function tryParse(?string $value): ?self
    {
        return match ($value) {
            self::List->value => self::List,
            self::Cards->value => self::Cards,
            default => null,
        };
    }

    public function isList(): bool
    {
        return $this === self::List;
    }

    public function isCards(): bool
    {
        return $this === self::Cards;
    }
}
