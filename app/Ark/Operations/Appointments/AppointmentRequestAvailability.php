<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Configuration: when the shop accepts public appointment *requests* (Lead intake).
 * Independent of Business Hours (telephony) and staff Appointment soft-capacity windows.
 *
 * Request preferences (preferred_period) guide staff booking.
 * Confirmed Appointments still require an exact starts_at.
 */
final class AppointmentRequestAvailability
{
    public const PERIOD_MORNING = 'morning';

    public const PERIOD_AFTERNOON = 'afternoon';

    public const PERIOD_ANY = 'any';

    /** @var list<int> */
    public const HORIZON_PRESETS = [7, 14, 30];

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
     * @return array{
     *     weekly: array<string, array{enabled: bool}>,
     *     horizon_days: int,
     *     minimum_notice_days: int,
     *     request_windows: array{
     *         morning: array{enabled: bool, open: string, close: string},
     *         afternoon: array{enabled: bool, open: string, close: string},
     *         flexible_enabled: bool,
     *         latest_appointment_arrival: string|null
     *     }
     * }
     */
    public static function defaultsFromSchedulingHours(?array $schedulingHours = null): array
    {
        $hours = SchedulingHours::normalize($schedulingHours);
        $weekly = [];

        foreach (self::WEEKDAYS as $day) {
            $weekly[$day] = [
                'enabled' => (bool) ($hours[$day]['enabled'] ?? false),
            ];
        }

        return [
            'weekly' => $weekly,
            'horizon_days' => 14,
            'minimum_notice_days' => 0,
            'request_windows' => ScheduleRequestWindows::defaults(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array{
     *     weekly: array<string, array{enabled: bool}>,
     *     horizon_days: int,
     *     minimum_notice_days: int,
     *     request_windows: array{
     *         morning: array{enabled: bool, open: string, close: string},
     *         afternoon: array{enabled: bool, open: string, close: string},
     *         flexible_enabled: bool,
     *         latest_appointment_arrival: string|null
     *     }
     * }
     */
    public static function normalize(?array $config, ?array $schedulingHoursFallback = null): array
    {
        $defaults = self::defaultsFromSchedulingHours($schedulingHoursFallback);

        if ($config === null || $config === []) {
            return $defaults;
        }

        $weekly = [];
        $weeklyInput = is_array($config['weekly'] ?? null) ? $config['weekly'] : [];

        foreach (self::WEEKDAYS as $day) {
            $row = $weeklyInput[$day] ?? $defaults['weekly'][$day];
            $weekly[$day] = [
                'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        $horizon = (int) ($config['horizon_days'] ?? $defaults['horizon_days']);
        $horizon = max(1, min(90, $horizon));

        $notice = (int) ($config['minimum_notice_days'] ?? $defaults['minimum_notice_days']);
        $notice = max(0, min(14, $notice));

        return [
            'weekly' => $weekly,
            'horizon_days' => $horizon,
            'minimum_notice_days' => $notice,
            'request_windows' => ScheduleRequestWindows::normalize($config),
        ];
    }

    /**
     * @return array{
     *     weekly: array<string, array{enabled: bool}>,
     *     horizon_days: int,
     *     minimum_notice_days: int,
     *     request_windows: array{
     *         morning: array{enabled: bool, open: string, close: string},
     *         afternoon: array{enabled: bool, open: string, close: string},
     *         flexible_enabled: bool,
     *         latest_appointment_arrival: string|null
     *     }
     * }
     */
    public static function forShop(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();

        return self::normalize(
            is_array($settings->appointment_request_availability) ? $settings->appointment_request_availability : null,
            $settings->schedulingHours(),
        );
    }

    /**
     * All known period values (including disabled) — for validating stored Lead metadata.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function periodOptions(): array
    {
        return [
            ['value' => self::PERIOD_MORNING, 'label' => 'Morning'],
            ['value' => self::PERIOD_AFTERNOON, 'label' => 'Afternoon'],
            ['value' => self::PERIOD_ANY, 'label' => 'Flexible'],
        ];
    }

    /**
     * Periods currently offered for customer requests.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function enabledPeriodOptions(?ShopSettings $settings = null): array
    {
        $windows = ScheduleRequestWindows::forShop($settings);
        $options = [];

        if ($windows['morning']['enabled']) {
            $options[] = ['value' => self::PERIOD_MORNING, 'label' => 'Morning'];
        }
        if ($windows['afternoon']['enabled']) {
            $options[] = ['value' => self::PERIOD_AFTERNOON, 'label' => 'Afternoon'];
        }
        if ($windows['flexible_enabled']) {
            $options[] = ['value' => self::PERIOD_ANY, 'label' => 'Flexible'];
        }

        return $options;
    }

    public static function periodLabel(string $period): string
    {
        foreach (self::periodOptions() as $option) {
            if ($option['value'] === $period) {
                return $option['label'];
            }
        }

        return 'Flexible';
    }

    /**
     * @return list<string>
     */
    public static function periodValues(): array
    {
        return array_column(self::periodOptions(), 'value');
    }
}
