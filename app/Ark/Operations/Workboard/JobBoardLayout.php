<?php

namespace App\Ark\Operations\Workboard;

/**
 * Job Board presentation: table list or compact cards over the same result set.
 * Legacy `board` query/cookie values map to Cards.
 */
enum JobBoardLayout: string
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

    public static function default(): self
    {
        return self::List;
    }

    public static function parse(?string $value): self
    {
        return match ($value) {
            self::Cards->value, 'board' => self::Cards,
            default => self::List,
        };
    }

    public static function tryParse(?string $value): ?self
    {
        return match ($value) {
            self::List->value => self::List,
            self::Cards->value, 'board' => self::Cards,
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
