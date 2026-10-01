<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Settings\ShopSettings;
use Carbon\Carbon;

final class ShopCustomerHoursPresentation
{
    /** @var list<string> */
    private const DAY_ORDER = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public static function summaryForCurrentShop(): ?string
    {
        $settings = ShopSettings::current();
        $callFlow = is_array($settings->telephony_call_flow) ? $settings->telephony_call_flow : [];

        return self::summary(
            is_array($callFlow['weekly_hours'] ?? null) ? $callFlow['weekly_hours'] : [],
        );
    }

    /**
     * @param  \App\Ark\Operations\Settings\ShopSettings  $settings
     */
    public static function summaryForShop(ShopSettings $settings): ?string
    {
        $callFlow = is_array($settings->telephony_call_flow) ? $settings->telephony_call_flow : [];

        return self::summary(
            is_array($callFlow['weekly_hours'] ?? null) ? $callFlow['weekly_hours'] : [],
        );
    }

    /**
     * @param  array<string, array{enabled?: bool, open?: string, close?: string}>  $weeklyHours
     */
    public static function summary(array $weeklyHours): ?string
    {
        if ($weeklyHours === []) {
            return null;
        }

        $segments = [];

        foreach (self::DAY_ORDER as $day) {
            $config = $weeklyHours[$day] ?? null;

            if (! is_array($config) || ! ($config['enabled'] ?? false)) {
                continue;
            }

            $open = self::formatClock((string) ($config['open'] ?? ''));
            $close = self::formatClock((string) ($config['close'] ?? ''));

            if ($open === null || $close === null) {
                continue;
            }

            $segments[] = [
                'day' => $day,
                'label' => self::dayLabel($day),
                'open' => $open,
                'close' => $close,
            ];
        }

        if ($segments === []) {
            return null;
        }

        return collect(self::compressSegments($segments))
            ->map(fn (array $segment): string => $segment['range_label'].' '.$segment['open'].' – '.$segment['close'])
            ->implode(' · ');
    }

    /**
     * @param  list<array{day: string, label: string, open: string, close: string}>  $segments
     * @return list<array{range_label: string, open: string, close: string}>
     */
    private static function compressSegments(array $segments): array
    {
        $compressed = [];
        $current = null;

        foreach ($segments as $segment) {
            $hoursKey = $segment['open'].'|'.$segment['close'];

            if (
                $current !== null
                && $current['hours_key'] === $hoursKey
                && self::isConsecutiveDay($current['last_day'], $segment['day'])
            ) {
                $current['last_day'] = $segment['day'];
                $current['range_label'] = $current['first_label'].'–'.$segment['label'];

                continue;
            }

            if ($current !== null) {
                $compressed[] = [
                    'range_label' => $current['range_label'],
                    'open' => $current['open'],
                    'close' => $current['close'],
                ];
            }

            $current = [
                'first_day' => $segment['day'],
                'last_day' => $segment['day'],
                'first_label' => $segment['label'],
                'range_label' => $segment['label'],
                'open' => $segment['open'],
                'close' => $segment['close'],
                'hours_key' => $hoursKey,
            ];
        }

        if ($current !== null) {
            $compressed[] = [
                'range_label' => $current['range_label'],
                'open' => $current['open'],
                'close' => $current['close'],
            ];
        }

        return $compressed;
    }

    private static function isConsecutiveDay(string $previousDay, string $nextDay): bool
    {
        $previousIndex = array_search($previousDay, self::DAY_ORDER, true);
        $nextIndex = array_search($nextDay, self::DAY_ORDER, true);

        return is_int($previousIndex)
            && is_int($nextIndex)
            && $nextIndex === $previousIndex + 1;
    }

    private static function dayLabel(string $day): string
    {
        return match ($day) {
            'monday' => 'Mon',
            'tuesday' => 'Tue',
            'wednesday' => 'Wed',
            'thursday' => 'Thu',
            'friday' => 'Fri',
            'saturday' => 'Sat',
            'sunday' => 'Sun',
            default => ucfirst($day),
        };
    }

    private static function formatClock(string $time): ?string
    {
        $time = trim($time);

        if ($time === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $time)->format('g:i A');
        } catch (\Throwable) {
            return null;
        }
    }
}
