<?php

namespace App\Ark\Operations\Workboard;

enum JobBoardLayout: string
{
    case Board = 'board';
    case List = 'list';

    public function label(): string
    {
        return match ($this) {
            self::Board => 'Board',
            self::List => 'List',
        };
    }

    public static function default(): self
    {
        return self::Board;
    }

    public static function parse(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    public function isList(): bool
    {
        return $this === self::List;
    }
}
