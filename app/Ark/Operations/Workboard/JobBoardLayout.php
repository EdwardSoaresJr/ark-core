<?php

namespace App\Ark\Operations\Workboard;

/**
 * Legacy layout values. The Job Board is table-first; board/list query/cookie
 * values are accepted and normalized to List so saved links keep working.
 */
enum JobBoardLayout: string
{
    case Board = 'board';
    case List = 'list';

    public function label(): string
    {
        return 'Job Board';
    }

    public static function default(): self
    {
        return self::List;
    }

    public static function parse(?string $value): self
    {
        // Board is retired as a presentation mode; always serve the table.
        return self::List;
    }

    public function isList(): bool
    {
        return true;
    }
}
