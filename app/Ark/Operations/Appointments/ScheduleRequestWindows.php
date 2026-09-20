<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

/**
 * Shop-configurable windows for appointment *request* preferences (morning / afternoon).
 * Not Appointment authority — confirmed bookings still require an exact starts_at.
 */
final class ScheduleRequestWindows
{
    public const DEFAULT_MORNING_OPEN = '09:00';

    public const DEFAULT_MORNING_CLOSE = '12:00';

    public const DEFAULT_AFTERNOON_OPEN = '12:00';

    public const DEFAULT_AFTERNOON_CLOSE = '16:00';

    /** Suggested clock when a shop explicitly enables a latest-arrival cutoff (not a Core default). */
    public const SUGGESTED_LATEST_ARRIVAL = '16:00';

    /**
     * @return array{
     *     morning: array{enabled: bool, open: string, close: string},
     *     afternoon: array{enabled: bool, open: string, close: string},
     *     flexible_enabled: bool,
     *     latest_appointment_arrival: string|null
     * }
     */
    public static function defaults(): array
    {
        return [
            'morning' => [
                'enabled' => true,
                'open' => self::DEFAULT_MORNING_OPEN,
                'close' => self::DEFAULT_MORNING_CLOSE,
            ],
            'afternoon' => [
                'enabled' => true,
                'open' => self::DEFAULT_AFTERNOON_OPEN,
                'close' => self::DEFAULT_AFTERNOON_CLOSE,
            ],
            'flexible_enabled' => true,
            // Optional shop policy — off by default. Afternoon request window close (16:00) is separate.
            'latest_appointment_arrival' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array{
     *     morning: array{enabled: bool, open: string, close: string},
     *     afternoon: array{enabled: bool, open: string, close: string},
     *     flexible_enabled: bool,
     *     latest_appointment_arrival: string|null
     * }
     */
    public static function normalize(?array $config): array
    {
        $defaults = self::defaults();
        $config = is_array($config) ? $config : [];

        // Backward compatible: arrival_windows from the unshipped daypart experiment.
        if (is_array($config['arrival_windows'] ?? null) && ! isset($config['request_windows'])) {
            $config['request_windows'] = $config['arrival_windows'];
        }

        $windows = is_array($config['request_windows'] ?? null)
            ? $config['request_windows']
            : (is_array($config) && (isset($config['morning']) || isset($config['afternoon']))
                ? $config
                : []);

        $morning = is_array($windows['morning'] ?? null) ? $windows['morning'] : [];
        $afternoon = is_array($windows['afternoon'] ?? null) ? $windows['afternoon'] : [];

        // Only apply when the shop explicitly configured a cutoff. Missing key ≠ 16:00 default.
        $latestRaw = array_key_exists('latest_appointment_arrival', $config)
            ? $config['latest_appointment_arrival']
            : (array_key_exists('latest_appointment_arrival', $windows)
                ? $windows['latest_appointment_arrival']
                : null);

        if ($latestRaw === '' || $latestRaw === false || $latestRaw === null) {
            $latest = null;
        } else {
            $latest = self::normalizeClock((string) $latestRaw, self::SUGGESTED_LATEST_ARRIVAL);
        }

        return [
            'morning' => [
                'enabled' => array_key_exists('enabled', $morning)
                    ? filter_var($morning['enabled'], FILTER_VALIDATE_BOOL)
                    : $defaults['morning']['enabled'],
                'open' => self::normalizeClock((string) ($morning['open'] ?? $defaults['morning']['open']), $defaults['morning']['open']),
                'close' => self::normalizeClock((string) ($morning['close'] ?? $defaults['morning']['close']), $defaults['morning']['close']),
            ],
            'afternoon' => [
                'enabled' => array_key_exists('enabled', $afternoon)
                    ? filter_var($afternoon['enabled'], FILTER_VALIDATE_BOOL)
                    : $defaults['afternoon']['enabled'],
                'open' => self::normalizeClock((string) ($afternoon['open'] ?? $defaults['afternoon']['open']), $defaults['afternoon']['open']),
                'close' => self::normalizeClock((string) ($afternoon['close'] ?? $defaults['afternoon']['close']), $defaults['afternoon']['close']),
            ],
            'flexible_enabled' => array_key_exists('flexible_enabled', $config)
                ? filter_var($config['flexible_enabled'], FILTER_VALIDATE_BOOL)
                : (array_key_exists('flexible_enabled', $windows)
                    ? filter_var($windows['flexible_enabled'], FILTER_VALIDATE_BOOL)
                    : $defaults['flexible_enabled']),
            'latest_appointment_arrival' => $latest,
        ];
    }

    /**
     * @return array{
     *     morning: array{enabled: bool, open: string, close: string},
     *     afternoon: array{enabled: bool, open: string, close: string},
     *     flexible_enabled: bool,
     *     latest_appointment_arrival: string|null
     * }
     */
    public static function forShop(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $availability = is_array($settings->appointment_request_availability)
            ? $settings->appointment_request_availability
            : [];

        return self::normalize($availability);
    }

    /**
     * @return array{open: string, close: string}|null
     */
    public static function windowForPeriod(string $period, ?ShopSettings $settings = null): ?array
    {
        $windows = self::forShop($settings);

        return match ($period) {
            AppointmentRequestAvailability::PERIOD_MORNING => $windows['morning']['enabled']
                ? ['open' => $windows['morning']['open'], 'close' => $windows['morning']['close']]
                : null,
            AppointmentRequestAvailability::PERIOD_AFTERNOON => $windows['afternoon']['enabled']
                ? ['open' => $windows['afternoon']['open'], 'close' => $windows['afternoon']['close']]
                : null,
            default => null,
        };
    }

    /**
     * Time options (H:i => label) filtered to a request period and latest-arrival cutoff.
     *
     * @return array<string, string>
     */
    public static function timeOptionsForPeriod(
        ?string $period,
        ?string $dateYmd = null,
        ?ShopSettings $settings = null,
        ?int $slotMinutes = null,
    ): array {
        $settings ??= ShopSettings::current();
        $slotMinutes ??= AppointmentSlotMinutes::resolve();
        $all = AppointmentSlotMinutes::timeOptions($slotMinutes);
        $windows = self::forShop($settings);

        $bounds = self::windowForPeriod((string) $period, $settings);
        $latest = $windows['latest_appointment_arrival'];

        $filtered = [];
        foreach ($all as $value => $label) {
            if ($bounds !== null && ! self::clockInHalfOpenRange($value, $bounds['open'], $bounds['close'])) {
                continue;
            }
            if ($latest !== null && self::clockCompare($value, $latest) > 0) {
                continue;
            }
            $filtered[$value] = $label;
        }

        // Preference is guidance — if filtering emptied the list, fall back to full day options
        // still respecting latest arrival when set.
        if ($filtered === [] && $bounds !== null) {
            foreach ($all as $value => $label) {
                if ($latest !== null && self::clockCompare($value, $latest) > 0) {
                    continue;
                }
                $filtered[$value] = $label;
            }
        }

        return $filtered !== [] ? $filtered : $all;
    }

    public static function isTimeOutsidePreferredPeriod(string $timeHi, string $period, ?ShopSettings $settings = null): bool
    {
        $bounds = self::windowForPeriod($period, $settings);
        if ($bounds === null) {
            return false;
        }

        return ! self::clockInHalfOpenRange($timeHi, $bounds['open'], $bounds['close']);
    }

    /**
     * Hard validation errors (block save). Soft warnings are returned separately.
     *
     * @param  array{morning: array{enabled: bool, open: string, close: string}, afternoon: array{enabled: bool, open: string, close: string}, flexible_enabled: bool, latest_appointment_arrival: string|null}  $windows
     * @return list<string>
     */
    public static function validationMessages(array $windows, ?array $schedulingHours = null): array
    {
        $messages = [];

        foreach (['morning', 'afternoon'] as $key) {
            $row = $windows[$key];
            if (self::clockCompare($row['open'], $row['close']) >= 0) {
                $messages[] = ucfirst($key).' window must open before it closes.';
            }
        }

        if ($windows['latest_appointment_arrival'] !== null) {
            $latest = $windows['latest_appointment_arrival'];
            foreach (['morning', 'afternoon'] as $key) {
                if ($windows[$key]['enabled']
                    && self::clockCompare($windows[$key]['open'], $latest) > 0) {
                    $messages[] = 'Latest appointment arrival is earlier than the '.ucfirst($key).' window opens.';
                }
            }
        }

        return $messages;
    }

    /**
     * Soft warnings for settings UI (save still allowed).
     *
     * @param  array{morning: array{enabled: bool, open: string, close: string}, afternoon: array{enabled: bool, open: string, close: string}, flexible_enabled: bool, latest_appointment_arrival: string|null}  $windows
     * @return list<string>
     */
    public static function validationWarnings(array $windows, ?array $schedulingHours = null): array
    {
        $warnings = [];
        $hours = SchedulingHours::normalize($schedulingHours);

        $anyDayCovers = false;
        foreach ($hours as $day) {
            if (! ($day['enabled'] ?? false)) {
                continue;
            }
            $dayOpen = (string) $day['open'];
            $dayClose = (string) $day['close'];
            if (self::clockCompare($windows['morning']['open'], $dayOpen) >= 0
                && self::clockCompare($windows['morning']['close'], $dayClose) <= 0
                && self::clockCompare($windows['afternoon']['open'], $dayOpen) >= 0
                && self::clockCompare($windows['afternoon']['close'], $dayClose) <= 0) {
                $anyDayCovers = true;
                break;
            }
        }

        if (! $anyDayCovers) {
            $warnings[] = 'Request windows should fall within shop scheduling hours on at least one open day.';
        }

        return $warnings;
    }

    public static function formatClockLabel(string $hi): string
    {
        return Carbon::createFromFormat('H:i', self::normalizeClock($hi, '09:00'))->format('g:i A');
    }

    private static function clockInHalfOpenRange(string $value, string $open, string $close): bool
    {
        return self::clockCompare($value, $open) >= 0 && self::clockCompare($value, $close) < 0;
    }

    private static function clockCompare(string $a, string $b): int
    {
        return strcmp(self::normalizeClock($a, '00:00'), self::normalizeClock($b, '00:00'));
    }

    private static function normalizeClock(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value) !== 1) {
            return $fallback;
        }

        [$h, $m] = array_map('intval', explode(':', $value));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return $fallback;
        }

        return sprintf('%02d:%02d', $h, $m);
    }
}
