<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Appointments\SchedulingHours;

/**
 * Public shop open/closed schedule (customer-facing, booking defaults, open-status).
 * Not appointment custom windows and not staff/technician schedules.
 */
final class BusinessHours
{
    /** @var list<string> */
    public const WEEKDAYS = SchedulingHours::WEEKDAYS;

    /**
     * @return array{
     *     weekly: array<string, array{enabled: bool, open: string, close: string}>,
     *     closed_dates: list<string>
     * }
     */
    public static function defaults(): array
    {
        return [
            'weekly' => [
                'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'tuesday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'wednesday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'thursday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'friday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'saturday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                'sunday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            ],
            'closed_dates' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{
     *     weekly: array<string, array{enabled: bool, open: string, close: string}>,
     *     closed_dates: list<string>
     * }
     */
    public static function normalize(?array $stored): array
    {
        $defaults = self::defaults();
        if ($stored === null || $stored === []) {
            return $defaults;
        }

        $weeklyIn = is_array($stored['weekly'] ?? null)
            ? $stored['weekly']
            : (is_array($stored['weekly_hours'] ?? null) ? $stored['weekly_hours'] : null);

        $closedIn = is_array($stored['closed_dates'] ?? null) ? $stored['closed_dates'] : [];

        return [
            'weekly' => SchedulingHours::normalize($weeklyIn),
            'closed_dates' => collect($closedIn)
                ->map(fn (mixed $date): string => trim((string) $date))
                ->filter(fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * Legacy embedded weekly/closed JSON → shop Business Hours document.
     *
     * @param  array<string, mixed>|null  $legacyHoursDocument
     * @return array{
     *     weekly: array<string, array{enabled: bool, open: string, close: string}>,
     *     closed_dates: list<string>
     * }
     */
    public static function fromLegacyDocument(?array $legacyHoursDocument): array
    {
        if ($legacyHoursDocument === null || $legacyHoursDocument === []) {
            return self::defaults();
        }

        return self::normalize([
            'weekly' => $legacyHoursDocument['weekly_hours'] ?? null,
            'closed_dates' => $legacyHoursDocument['closed_dates'] ?? [],
        ]);
    }
}
