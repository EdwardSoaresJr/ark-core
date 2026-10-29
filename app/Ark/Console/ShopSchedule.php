<?php

namespace App\Ark\Console;

use App\Ark\Operations\OperationsFeatures;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use RuntimeException;

/**
 * Shop tasks for one database. The caller selects that database first.
 */
final class ShopSchedule
{
    /** @var array<int, string> */
    private static array $commands = [];

    public static function register(Schedule $schedule): void
    {
        self::$commands = [];

        self::command($schedule, 'shop-excellence:owner-digest')
            ->dailyAt(ShopExcellenceTargets::ownerDigestTime())
            ->timezone(ShopDisplayTimezone::resolve())
            ->when(fn (): bool => ShopExcellenceTargets::ownerDigestEnabled())
            ->onFailure(function (): void {
                report(new RuntimeException('Scheduled owner daily digest failed.'));
            })
            ->appendOutputTo(storage_path('logs/scheduler-owner-digest.log'));

        self::command($schedule, 'communications:daily-coaching-digest')
            ->dailyAt(ShopExcellenceTargets::coachingDigestTime())
            ->timezone(ShopDisplayTimezone::resolve())
            ->when(fn (): bool => ShopExcellenceTargets::coachingDigestEnabled())
            ->onFailure(function (): void {
                report(new RuntimeException('Scheduled daily coaching digest failed.'));
            })
            ->appendOutputTo(storage_path('logs/scheduler-coaching-digest.log'));

        self::command($schedule, 'comms:escalate-unhandled')
            ->everyMinute()
            ->withoutOverlapping();

        self::command($schedule, 'time-clock:sync-auto')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        self::command($schedule, 'comms:reconcile-stale-call-sessions')
            ->everyMinute()
            ->withoutOverlapping();

        self::command($schedule, 'appointments:send-reminders')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->when(fn (): bool => OperationsFeatures::appointmentsEnabled());

        self::command($schedule, 'ark:platform-heartbeat')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }

    /**
     * Artisan command names that are due for the shop already selected on this process.
     *
     * @return list<string>
     */
    public static function dueArtisanCommands(): array
    {
        $schedule = new Schedule;
        self::register($schedule);
        $app = app();
        $due = [];
        foreach ($schedule->dueEvents($app) as $event) {
            if (! $event->filtersPass($app)) {
                continue;
            }
            $name = self::$commands[spl_object_id($event)] ?? null;
            if (is_string($name)) {
                $due[] = $name;
            }
        }

        return $due;
    }

    private static function command(Schedule $schedule, string $name): Event
    {
        $event = $schedule->command($name);
        self::$commands[spl_object_id($event)] = $name;

        return $event;
    }
}
