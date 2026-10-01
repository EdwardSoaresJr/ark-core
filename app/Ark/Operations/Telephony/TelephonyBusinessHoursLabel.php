<?php

namespace App\Ark\Operations\Telephony;

use Carbon\Carbon;

/**
 * Formats telephony weekly hours for customer-facing copy (public lead intake).
 */
final class TelephonyBusinessHoursLabel
{
    /** @var array<string, string> */
    private const DAY_SHORT = [
        'monday' => 'Mon',
        'tuesday' => 'Tue',
        'wednesday' => 'Wed',
        'thursday' => 'Thu',
        'friday' => 'Fri',
        'saturday' => 'Sat',
        'sunday' => 'Sun',
    ];

    public static function fromCallFlow(?TelephonyCallFlowSettings $settings = null): string
    {
        $settings ??= TelephonyCallFlowSettings::fromShopSettings();
        $weekly = $settings->weeklyHours();

        /** @var list<array{start: string, end: string, open: string, close: string}> */
        $segments = [];
        $current = null;

        foreach (TelephonyCallFlowSettings::WEEKDAYS as $day) {
            $hours = $weekly[$day] ?? null;

            if ($hours === null || ! $hours['enabled']) {
                if ($current !== null) {
                    $segments[] = $current;
                    $current = null;
                }

                continue;
            }

            $scheduleKey = $hours['open'].'|'.$hours['close'];

            if (
                $current !== null
                && $current['open'] === $hours['open']
                && $current['close'] === $hours['close']
                && self::isNextWeekday($current['end'], $day)
            ) {
                $current['end'] = $day;

                continue;
            }

            if ($current !== null) {
                $segments[] = $current;
            }

            $current = [
                'start' => $day,
                'end' => $day,
                'open' => $hours['open'],
                'close' => $hours['close'],
            ];
        }

        if ($current !== null) {
            $segments[] = $current;
        }

        if ($segments === []) {
            return 'Closed';
        }

        $label = collect($segments)
            ->map(fn (array $segment): string => self::formatSegment($segment))
            ->implode(' · ');

        $closedWeekend = self::closedWeekendSuffix($weekly);

        return $closedWeekend !== null ? $label.' · '.$closedWeekend : $label;
    }

    /**
     * @param  array<string, array{enabled: bool, open: string, close: string}|null>  $weekly
     */
    private static function closedWeekendSuffix(array $weekly): ?string
    {
        $saturdayOpen = ($weekly['saturday']['enabled'] ?? false) === true;
        $sundayOpen = ($weekly['sunday']['enabled'] ?? false) === true;

        if ($saturdayOpen && $sundayOpen) {
            return null;
        }

        if (! $saturdayOpen && ! $sundayOpen) {
            return 'Sat–Sun: Closed';
        }

        if (! $saturdayOpen) {
            return 'Sat: Closed';
        }

        return 'Sun: Closed';
    }

    /**
     * @param  array{start: string, end: string, open: string, close: string}  $segment
     */
    private static function formatSegment(array $segment): string
    {
        $dayLabel = $segment['start'] === $segment['end']
            ? self::DAY_SHORT[$segment['start']]
            : self::DAY_SHORT[$segment['start']].'–'.self::DAY_SHORT[$segment['end']];

        return sprintf(
            '%s: %s – %s',
            $dayLabel,
            self::formatClockTime($segment['open']),
            self::formatClockTime($segment['close']),
        );
    }

    private static function formatClockTime(string $time): string
    {
        return Carbon::createFromFormat('H:i', $time)->format('g:i A');
    }

    private static function isNextWeekday(string $previousDay, string $day): bool
    {
        $order = TelephonyCallFlowSettings::WEEKDAYS;
        $previousIndex = array_search($previousDay, $order, true);
        $dayIndex = array_search($day, $order, true);

        return is_int($previousIndex)
            && is_int($dayIndex)
            && $dayIndex === $previousIndex + 1;
    }
}
