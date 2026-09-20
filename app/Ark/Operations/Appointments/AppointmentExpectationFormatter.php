<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Canonical wording for confirmed appointments vs request preferences.
 * Confirmed Appointment copy always uses the exact starts_at clock.
 */
final class AppointmentExpectationFormatter
{
    public static function confirmedWhenLabel(Appointment $appointment): string
    {
        return ShopDisplayTimezone::format($appointment->starts_at, 'D M j \\a\\t g:i A')
            ?? 'soon';
    }

    public static function confirmedTimeFragment(Appointment $appointment): string
    {
        return ShopDisplayTimezone::format($appointment->starts_at, 'g:i A')
            ?? 'your appointment time';
    }

    public static function confirmedShowHeader(Appointment $appointment): string
    {
        return ShopDisplayTimezone::format($appointment->starts_at, 'l, M j · g:i A')
            ?? '—';
    }

    /**
     * Short request preference: "Friday afternoon"
     */
    public static function requestedLabel(?string $dateYmd, ?string $period, ?ShopSettings $settings = null): string
    {
        $periodLabel = AppointmentRequestAvailability::periodLabel((string) $period);
        if ($dateYmd === null || $dateYmd === '') {
            return $periodLabel;
        }

        $day = ShopDisplayTimezone::parseLocal($dateYmd.' 12:00')?->format('l')
            ?? $dateYmd;

        if ($period === AppointmentRequestAvailability::PERIOD_ANY || $period === null || $period === '') {
            return $day.' · flexible';
        }

        return $day.' '.strtolower($periodLabel);
    }

    /**
     * Detailed request preference: "Friday afternoon (12:00 PM–4:00 PM)"
     */
    public static function requestedDetail(?string $dateYmd, ?string $period, ?ShopSettings $settings = null): string
    {
        $base = self::requestedLabel($dateYmd, $period, $settings);
        $bounds = $period ? ScheduleRequestWindows::windowForPeriod($period, $settings) : null;
        if ($bounds === null) {
            return $base;
        }

        return $base.' ('.ScheduleRequestWindows::formatClockLabel($bounds['open'])
            .'–'.ScheduleRequestWindows::formatClockLabel($bounds['close']).')';
    }
}
