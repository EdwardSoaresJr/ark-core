<?php

namespace App\Ark\Operations\Work;

enum WorkQueue: string
{
    case Tasks = 'tasks';
    case FollowUps = 'follow-ups';
    case Scheduled = 'scheduled';
    case Comms = 'comms';
    case Decisions = 'decisions';

    public function label(): string
    {
        return match ($this) {
            self::Tasks => 'Tasks',
            self::FollowUps => 'Follow-Ups',
            self::Scheduled => 'Scheduled',
            self::Comms => 'Communications',
            self::Decisions => 'Customer Decisions',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Tasks => 'Internal shop work — vendors, warranty, tools, coordination.',
            self::FollowUps => 'Customer callbacks and revenue waiting on the next touch.',
            self::Scheduled => 'Customer decisions snoozed until a future day.',
            self::Comms => 'Customer communication pressure requiring action.',
            self::Decisions => 'Shop-wide dollars waiting on a customer choice.',
        };
    }

    public function bandClass(): string
    {
        return match ($this) {
            self::Tasks => 'ops-home-band--tasks',
            self::FollowUps => 'ops-home-band--follow-ups',
            self::Scheduled => 'ops-home-band--scheduled',
            self::Comms => 'ops-home-band--customer',
            self::Decisions => 'ops-home-band--decision',
        };
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(
            static fn (self $queue): string => $queue->value,
            self::cases(),
        );
    }
}
