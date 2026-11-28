<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Str;

/**
 * Customer-facing appointment email copy for installation SMTP / managed delivery.
 */
final class AppointmentEmailCopy
{
    /**
     * @return array{subject: string, intro: string, lines: list<string>, kind: string}
     */
    public static function confirmation(Appointment $appointment): array
    {
        $shop = self::shopName();
        $when = AppointmentExpectationFormatter::confirmedWhenLabel($appointment);

        return [
            'kind' => 'confirmation',
            'subject' => "Appointment confirmation from {$shop}",
            'intro' => "{$shop} confirmed your appointment.",
            'lines' => array_values(array_filter([
                'When: '.$when,
                'For: '.self::concernSnippet($appointment),
                self::vehicleLine($appointment),
            ])),
        ];
    }

    /**
     * @return array{subject: string, intro: string, lines: list<string>, kind: string}
     */
    public static function dayBeforeReminder(Appointment $appointment): array
    {
        $shop = self::shopName();
        $time = AppointmentExpectationFormatter::confirmedTimeFragment($appointment);

        return [
            'kind' => 'reminder_day_before',
            'subject' => "Appointment reminder from {$shop}",
            'intro' => "{$shop} reminder: your appointment is tomorrow.",
            'lines' => array_values(array_filter([
                'Time: '.$time,
                'For: '.self::concernSnippet($appointment),
                self::vehicleLine($appointment),
            ])),
        ];
    }

    /**
     * @return array{subject: string, intro: string, lines: list<string>, kind: string}
     */
    public static function hoursBeforeReminder(Appointment $appointment, int $hours): array
    {
        $shop = self::shopName();
        $time = AppointmentExpectationFormatter::confirmedTimeFragment($appointment);
        $window = $hours === 1 ? 'about 1 hour' : "about {$hours} hours";

        return [
            'kind' => 'reminder_hours_before',
            'subject' => "Appointment reminder from {$shop}",
            'intro' => "{$shop} reminder: your appointment is in {$window}.",
            'lines' => array_values(array_filter([
                'Time: '.$time,
                'For: '.self::concernSnippet($appointment),
                self::vehicleLine($appointment),
            ])),
        ];
    }

    /**
     * @return array{subject: string, intro: string, lines: list<string>, kind: string}
     */
    public static function canceled(Appointment $appointment): array
    {
        $shop = self::shopName();
        $when = AppointmentExpectationFormatter::confirmedWhenLabel($appointment);

        return [
            'kind' => 'canceled',
            'subject' => "Appointment canceled — {$shop}",
            'intro' => "{$shop} canceled your appointment.",
            'lines' => array_values(array_filter([
                'Previously scheduled: '.$when,
                'For: '.self::concernSnippet($appointment),
                'Contact the shop if you need to rebook.',
            ])),
        ];
    }

    /**
     * @return array{subject: string, intro: string, lines: list<string>, kind: string}
     */
    public static function rescheduled(Appointment $appointment, ?string $previousWhen = null): array
    {
        $shop = self::shopName();
        $when = AppointmentExpectationFormatter::confirmedWhenLabel($appointment);

        return [
            'kind' => 'rescheduled',
            'subject' => "Appointment rescheduled — {$shop}",
            'intro' => "{$shop} moved your appointment.",
            'lines' => array_values(array_filter([
                filled($previousWhen) ? 'Previously: '.$previousWhen : null,
                'New time: '.$when,
                'For: '.self::concernSnippet($appointment),
            ])),
        ];
    }

    private static function concernSnippet(Appointment $appointment): string
    {
        $concern = trim((string) $appointment->concern);

        if ($concern === '') {
            return 'your visit';
        }

        return Str::limit($concern, 120, '…');
    }

    private static function vehicleLine(Appointment $appointment): ?string
    {
        $appointment->loadMissing('vehicle');
        $vehicle = $appointment->vehicle;
        if ($vehicle === null) {
            return null;
        }

        $label = trim(implode(' ', array_filter([
            $vehicle->year ?? null,
            $vehicle->make ?? null,
            $vehicle->model ?? null,
        ])));

        return $label !== '' ? 'Vehicle: '.$label : null;
    }

    private static function shopName(): string
    {
        $name = trim((string) (ShopSettings::current()->shop_name ?? ''));

        return $name !== '' ? $name : 'Your shop';
    }
}
