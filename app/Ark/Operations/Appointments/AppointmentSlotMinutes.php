<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

/**
 * Shop-configured schedule increments — calendar slots and appointment time selects.
 */
final class AppointmentSlotMinutes
{
    public const DEFAULT = 30;

    /** @var list<int> */
    public const ALLOWED = [15, 30, 60];

    public static function resolve(?ShopSettings $settings = null): int
    {
        $settings ??= ShopSettings::current();
        $minutes = (int) ($settings->appointment_slot_minutes ?? self::DEFAULT);

        return in_array($minutes, self::ALLOWED, true) ? $minutes : self::DEFAULT;
    }

    /**
     * @return array<int, string> minutes => label
     */
    public static function settingOptions(): array
    {
        return [
            15 => '15 minutes',
            30 => '30 minutes',
            60 => '60 minutes',
        ];
    }

    /**
     * Clock times for selects (24h values → shop-facing labels).
     *
     * @return array<string, string> H:i => g:i A
     */
    public static function timeOptions(?int $slotMinutes = null): array
    {
        $slotMinutes = $slotMinutes ?? self::resolve();
        $options = [];

        for ($minuteOfDay = 0; $minuteOfDay < 24 * 60; $minuteOfDay += $slotMinutes) {
            $hours = intdiv($minuteOfDay, 60);
            $minutes = $minuteOfDay % 60;
            $value = sprintf('%02d:%02d', $hours, $minutes);
            $options[$value] = Carbon::createFromTime($hours, $minutes)->format('g:i A');
        }

        return $options;
    }

    public static function snapTimeString(string $hi, ?int $slotMinutes = null): string
    {
        $slotMinutes = $slotMinutes ?? self::resolve();
        $parts = array_map('intval', explode(':', $hi));
        $total = ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0);
        $snapped = (int) (round($total / $slotMinutes) * $slotMinutes);
        $snapped = max(0, min(24 * 60 - $slotMinutes, $snapped));

        return sprintf('%02d:%02d', intdiv($snapped, 60), $snapped % 60);
    }

    public static function stepSeconds(?int $slotMinutes = null): int
    {
        return ($slotMinutes ?? self::resolve()) * 60;
    }

    /**
     * Suggested appointment lengths (calendar block), stepped by slot size.
     *
     * @return array<int, string> minutes => label
     */
    public static function durationOptions(?int $slotMinutes = null): array
    {
        $slotMinutes = $slotMinutes ?? self::resolve();
        $options = [];

        foreach ([1, 2, 3, 4, 6, 8, 10, 12, 16] as $multiples) {
            $minutes = $slotMinutes * $multiples;
            if ($minutes > 8 * 60) {
                break;
            }
            $options[$minutes] = self::durationLabel($minutes);
        }

        return $options;
    }

    public static function durationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = $minutes / 60;
        if (fmod($hours, 1.0) === 0.0) {
            $whole = (int) $hours;

            return $whole === 1 ? '1 hour' : $whole.' hours';
        }

        $whole = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $whole.'h '.$remainder.'m';
    }

    public static function snapDurationMinutes(int $minutes, ?int $slotMinutes = null): int
    {
        $slotMinutes = $slotMinutes ?? self::resolve();
        $options = array_keys(self::durationOptions($slotMinutes));
        $min = $options[0] ?? $slotMinutes;
        $max = $options[array_key_last($options)] ?? (8 * 60);
        $minutes = max($min, min($max, $minutes));
        $snapped = (int) (round($minutes / $slotMinutes) * $slotMinutes);

        return max($min, min($max, $snapped));
    }
}
