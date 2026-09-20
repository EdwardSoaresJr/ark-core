<?php

namespace App\Ark\Operations\Appointments;

use Carbon\CarbonInterface;

/**
 * Shop / technician weekly open windows for Scheduling Workspace.
 * Same shape as telephony weekly_hours (Business Hours).
 *
 * Shop default: inherit Business Hours. Custom `scheduling_hours` only when
 * the shop blacklists days or narrows open windows for staff booking.
 */
final class SchedulingHours
{
    /** @var list<string> */
    public const WEEKDAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * Legacy seed shape — not the product default. Prefer Business Hours inherit.
     *
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    public static function defaultWeekly(): array
    {
        return [
            'monday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
            'tuesday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
            'wednesday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
            'thursday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
            'friday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
            'saturday' => ['enabled' => false, 'open' => '08:00', 'close' => '12:00'],
            'sunday' => ['enabled' => false, 'open' => '08:00', 'close' => '12:00'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $weekly
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    public static function normalize(?array $weekly): array
    {
        $defaults = self::defaultWeekly();

        if ($weekly === null || $weekly === []) {
            return $defaults;
        }

        $normalized = [];

        foreach ($defaults as $day => $defaultDay) {
            $row = $weekly[$day] ?? $defaultDay;
            $normalized[$day] = [
                'enabled' => filter_var($row['enabled'] ?? $defaultDay['enabled'], FILTER_VALIDATE_BOOL),
                'open' => self::normalizeClock((string) ($row['open'] ?? $defaultDay['open']), $defaultDay['open']),
                'close' => self::normalizeClock((string) ($row['close'] ?? $defaultDay['close']), $defaultDay['close']),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $businessWeekly
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    public static function fromBusinessHours(?array $businessWeekly): array
    {
        return self::normalize($businessWeekly);
    }

    /**
     * Stored null/empty means follow Business Hours (not the legacy Mon–Fri seed).
     *
     * @param  array<string, mixed>|null  $stored
     */
    public static function isCustom(?array $stored): bool
    {
        return $stored !== null && $stored !== [];
    }

    /**
     * Historical install/seed rows that predate Business Hours inherit.
     *
     * @param  array<string, mixed>|null  $stored
     */
    public static function matchesLegacySeed(?array $stored): bool
    {
        if (! self::isCustom($stored)) {
            return false;
        }

        return self::normalize($stored) === self::defaultWeekly();
    }

    /**
     * Next slot-aligned start inside an open window, at or after $from.
     *
     * Scheduling almost never targets "right now": after close (or before
     * open) the useful default is the next opening, not tonight at 9 PM.
     *
     * @param  array<string, array{enabled: bool, open: string, close: string}>  $weekly
     */
    public static function nextOpenSlot(array $weekly, CarbonInterface $from, int $slotMinutes): CarbonInterface
    {
        $cursor = $from->copy();

        for ($day = 0; $day < 14; $day++) {
            $row = $weekly[strtolower($cursor->englishDayOfWeek)] ?? null;

            if ($row !== null && $row['enabled']) {
                $open = $cursor->copy()->setTimeFromTimeString($row['open']);
                $close = $cursor->copy()->setTimeFromTimeString($row['close']);

                $candidate = $cursor->greaterThan($open) ? $cursor->copy() : $open->copy();

                $minutes = $candidate->hour * 60 + $candidate->minute;
                $snapped = (int) (ceil($minutes / $slotMinutes) * $slotMinutes);
                $candidate = $candidate->copy()->startOfDay()->addMinutes($snapped);

                if ($candidate->copy()->addMinutes($slotMinutes)->lessThanOrEqualTo($close)) {
                    return $candidate;
                }
            }

            $cursor = $cursor->copy()->addDay()->startOfDay();
        }

        return $from->copy();
    }

    /**
     * @param  array<string, array{enabled: bool, open: string, close: string}>  $weekly
     */
    public static function contains(array $weekly, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        if ($startsAt->toDateString() !== $endsAt->toDateString()) {
            return false;
        }

        $day = strtolower($startsAt->englishDayOfWeek);
        $row = $weekly[$day] ?? null;

        if ($row === null || ! $row['enabled']) {
            return false;
        }

        $open = $startsAt->copy()->setTimeFromTimeString($row['open']);
        $close = $startsAt->copy()->setTimeFromTimeString($row['close']);

        return $startsAt->greaterThanOrEqualTo($open)
            && $endsAt->lessThanOrEqualTo($close)
            && $startsAt->lessThan($endsAt);
    }

    private static function normalizeClock(string $value, string $fallback): string
    {
        $value = trim($value);

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) === 1) {
            return substr($value, 0, 5);
        }

        return $fallback;
    }
}
