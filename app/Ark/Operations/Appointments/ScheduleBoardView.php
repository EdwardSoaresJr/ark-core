<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Support\Carbon;

enum ScheduleBoardView: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Day',
            self::Week => 'Week',
            self::Month => 'Month',
        };
    }

    public static function default(): self
    {
        return self::Day;
    }

    public static function parse(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(Carbon $focus): array
    {
        $focus = $focus->copy()->startOfDay();

        return match ($this) {
            self::Day => [$focus->copy(), $focus->copy()->endOfDay()],
            self::Week => [
                $focus->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $focus->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)->endOfDay(),
            ],
            self::Month => [
                $focus->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $focus->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ],
        };
    }

    public function navPrev(Carbon $focus): Carbon
    {
        return match ($this) {
            self::Day => $focus->copy()->subDay(),
            self::Week => $focus->copy()->subWeek(),
            self::Month => $focus->copy()->subMonthNoOverflow()->startOfMonth(),
        };
    }

    public function navNext(Carbon $focus): Carbon
    {
        return match ($this) {
            self::Day => $focus->copy()->addDay(),
            self::Week => $focus->copy()->addWeek(),
            self::Month => $focus->copy()->addMonthNoOverflow()->startOfMonth(),
        };
    }

    public function focusLabel(Carbon $focus): string
    {
        return match ($this) {
            self::Day => $focus->format('l, M j, Y'),
            self::Week => 'Week of '.$focus->copy()->startOfWeek(Carbon::MONDAY)->format('M j, Y'),
            self::Month => $focus->format('F Y'),
        };
    }
}
