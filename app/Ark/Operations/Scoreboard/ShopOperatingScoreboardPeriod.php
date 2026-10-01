<?php

namespace App\Ark\Operations\Scoreboard;

use App\Ark\Operations\Reports\OperationalReportDateScope;
use Illuminate\Support\Carbon;

final class ShopOperatingScoreboardPeriod
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['key' => 'today', 'label' => 'Today'],
            ['key' => 'this_week', 'label' => 'This week'],
            ['key' => 'last_7', 'label' => 'Last 7 days'],
            ['key' => 'this_month', 'label' => 'This month'],
            ['key' => 'last_30', 'label' => 'Last 30 days'],
            ['key' => 'last_90', 'label' => 'Last 90 days'],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     from: Carbon,
     *     to: Carbon,
     *     previous_from: Carbon|null,
     *     previous_to: Carbon|null,
     *     previous_label: string|null
     * }
     */
    public static function resolve(?string $key): array
    {
        $key = collect(self::options())->contains(fn (array $option): bool => $option['key'] === $key)
            ? (string) $key
            : 'this_month';

        $now = OperationalReportDateScope::shopNow();
        [$start, $end, $previousStart, $previousEnd, $label, $previousLabel] = match ($key) {
            'today' => self::today($now),
            'this_week' => self::thisWeek($now),
            'last_7' => self::rollingDays($now, 7, 'Last 7 days', 'Prior 7 days'),
            'last_30' => self::rollingDays($now, 30, 'Last 30 days', 'Prior 30 days'),
            'last_90' => self::rollingDays($now, 90, 'Last 90 days', 'Prior 90 days'),
            default => self::thisMonth($now),
        };

        [$from, $to] = OperationalReportDateScope::resolveRange($start->toDateString(), $end->toDateString());
        $previous = self::previousWindow($previousStart, $previousEnd);

        return [
            'key' => $key,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'previous_from' => $previous['from'],
            'previous_to' => $previous['to'],
            'previous_label' => $previous['from'] === null ? null : $previousLabel,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string, 5: string}
     */
    private static function today(Carbon $now): array
    {
        $start = $now->copy()->startOfDay();
        $previous = $start->copy()->subDay();

        return [$start, $now->copy(), $previous, $previous->copy()->endOfDay(), 'Today', 'Yesterday'];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string, 5: string}
     */
    private static function thisWeek(Carbon $now): array
    {
        $start = $now->copy()->startOfWeek();
        $elapsed = (int) $start->copy()->startOfDay()->diffInDays($now->copy()->startOfDay());
        $previousStart = $start->copy()->subWeek();
        $previousEnd = $previousStart->copy()->addDays($elapsed)->endOfDay();

        return [$start, $now->copy(), $previousStart, $previousEnd, 'This week', 'Prior week'];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string, 5: string}
     */
    private static function thisMonth(Carbon $now): array
    {
        $start = $now->copy()->startOfMonth();
        $previousStart = $start->copy()->subMonth()->startOfMonth();
        $previousEnd = $previousStart->copy()->day(min($now->day, $previousStart->daysInMonth))->endOfDay();

        return [$start, $now->copy(), $previousStart, $previousEnd, 'This month', 'Prior month to date'];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string, 5: string}
     */
    private static function rollingDays(Carbon $now, int $days, string $label, string $previousLabel): array
    {
        $end = $now->copy();
        $start = $now->copy()->subDays($days - 1)->startOfDay();
        $previousEnd = $start->copy()->subDay()->endOfDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();

        return [$start, $end, $previousStart, $previousEnd, $label, $previousLabel];
    }

    /**
     * @return array{from: Carbon|null, to: Carbon|null}
     */
    private static function previousWindow(Carbon $start, Carbon $end): array
    {
        $floor = OperationalReportDateScope::trustworthyDataStartsAt();

        if ($end->lessThan($floor)) {
            return ['from' => null, 'to' => null];
        }

        [$from, $to] = OperationalReportDateScope::resolveRange($start->toDateString(), $end->toDateString());

        return ['from' => $from, 'to' => $to];
    }
}
