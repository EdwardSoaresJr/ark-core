<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * The shop's online booking configuration (shop_settings.online_booking).
 *
 * Availability days, request windows, horizon, notice and date exceptions stay in
 * AppointmentRequestAvailability; this holds what the customer sees and the intake rules.
 * Every read normalizes, so a missing or partial value behaves like the defaults.
 */
final class BookingSettings
{
    public const FIELD_HIDDEN = 'hidden';

    public const FIELD_OPTIONAL = 'optional';

    public const FIELD_REQUIRED = 'required';

    public const FIELD_LEVELS = [self::FIELD_HIDDEN, self::FIELD_OPTIONAL, self::FIELD_REQUIRED];

    public const MAX_VEHICLES = 10;

    public const DEFAULT_CONCERNS = [
        'Check Engine Light',
        'Diagnostics',
        'Strange Noise',
        'Brakes',
        'A/C',
        'Maintenance',
        'Electrical',
        'Suspension / Steering',
        'Pre-Purchase Inspection',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'mode' => BookingMode::Request->value,
            'title' => 'Request an appointment',
            'intro' => 'Tell us what the vehicle is doing and when you would like to come in.',
            'instructions' => '',
            'request_confirmation' => 'We received your request. The shop will contact you to confirm a time. Your appointment is not confirmed until they do.',
            'instant_confirmation' => 'Your appointment is booked. We look forward to seeing you.',
            'concern_choices' => self::DEFAULT_CONCERNS,
            'fields' => [
                'email' => self::FIELD_OPTIONAL,
                'vehicle' => self::FIELD_OPTIONAL,
                'concern' => self::FIELD_REQUIRED,
                'vin' => false,
                'contact_preference' => true,
                'drivability' => false,
            ],
            'max_vehicles' => 1,
            'service_area' => [
                'postal_codes' => [],
                'message' => '',
            ],
            'limits' => [
                'daily_requests' => null,
                'respect_capacity' => true,
                'concurrent_appointments' => null,
            ],
            'popup' => [
                'button_label' => 'Book an appointment',
                'floating_button' => false,
                'allowed_domains' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forShop(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $stored = $settings->getAttribute('online_booking');

        return self::normalize(is_array($stored) ? $stored : null);
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>
     */
    public static function normalize(?array $config): array
    {
        $defaults = self::defaults();
        $config ??= [];

        $fieldsIn = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $fields = $defaults['fields'];
        foreach (['email', 'vehicle', 'concern'] as $key) {
            $level = (string) ($fieldsIn[$key] ?? $fields[$key]);
            $fields[$key] = in_array($level, self::FIELD_LEVELS, true) ? $level : $fields[$key];
        }
        foreach (['vin', 'contact_preference', 'drivability'] as $key) {
            if (array_key_exists($key, $fieldsIn)) {
                $fields[$key] = filter_var($fieldsIn[$key], FILTER_VALIDATE_BOOL);
            }
        }

        $concerns = is_array($config['concern_choices'] ?? null)
            ? self::cleanList($config['concern_choices'], 60, 20)
            : $defaults['concern_choices'];

        $areaIn = is_array($config['service_area'] ?? null) ? $config['service_area'] : [];
        $limitsIn = is_array($config['limits'] ?? null) ? $config['limits'] : [];
        $popupIn = is_array($config['popup'] ?? null) ? $config['popup'] : [];

        $mode = BookingMode::tryFrom((string) ($config['mode'] ?? '')) ?? BookingMode::Request;

        return [
            'enabled' => array_key_exists('enabled', $config) ? filter_var($config['enabled'], FILTER_VALIDATE_BOOL) : $defaults['enabled'],
            'mode' => $mode->value,
            'title' => self::text($config['title'] ?? null, 120, $defaults['title']),
            'intro' => self::text($config['intro'] ?? null, 500, $defaults['intro'], allowEmpty: true),
            'instructions' => self::text($config['instructions'] ?? null, 1000, '', allowEmpty: true),
            'request_confirmation' => self::text($config['request_confirmation'] ?? null, 1000, $defaults['request_confirmation']),
            'instant_confirmation' => self::text($config['instant_confirmation'] ?? null, 1000, $defaults['instant_confirmation']),
            'concern_choices' => $concerns,
            'fields' => $fields,
            'max_vehicles' => max(1, min(self::MAX_VEHICLES, (int) ($config['max_vehicles'] ?? $defaults['max_vehicles']))),
            'service_area' => [
                'postal_codes' => self::postalCodes($areaIn['postal_codes'] ?? []),
                'message' => self::text($areaIn['message'] ?? null, 300, '', allowEmpty: true),
            ],
            'limits' => [
                'daily_requests' => self::positiveOrNull($limitsIn['daily_requests'] ?? null, 500),
                'respect_capacity' => array_key_exists('respect_capacity', $limitsIn)
                    ? filter_var($limitsIn['respect_capacity'], FILTER_VALIDATE_BOOL)
                    : true,
                'concurrent_appointments' => self::positiveOrNull($limitsIn['concurrent_appointments'] ?? null, 50),
            ],
            'popup' => [
                'button_label' => self::text($popupIn['button_label'] ?? null, 60, $defaults['popup']['button_label']),
                'floating_button' => filter_var($popupIn['floating_button'] ?? false, FILTER_VALIDATE_BOOL),
                'allowed_domains' => self::domains($popupIn['allowed_domains'] ?? []),
            ],
        ];
    }

    public static function mode(?ShopSettings $settings = null): BookingMode
    {
        return BookingMode::from(self::forShop($settings)['mode']);
    }

    /**
     * @param  mixed  $value  list or newline/comma separated text
     * @return list<string>
     */
    public static function domains(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($items ?: [] as $item) {
            $host = strtolower(trim((string) $item));
            $host = preg_replace('#^https?://#', '', $host) ?? '';
            $host = explode('/', $host)[0];
            $host = preg_replace('/^www\./', '', $host) ?? '';
            if ($host !== '' && preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1) {
                $out[$host] = true;
            }
        }

        return array_slice(array_keys($out), 0, 20);
    }

    /**
     * @param  mixed  $value  list or newline/comma separated text
     * @return list<string>
     */
    public static function postalCodes(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($items ?: [] as $item) {
            $code = strtoupper(preg_replace('/\s+/', '', (string) $item) ?? '');
            if ($code !== '' && preg_match('/^[A-Z0-9-]{3,10}$/', $code) === 1) {
                $out[$code] = true;
            }
        }

        return array_slice(array_map('strval', array_keys($out)), 0, 500);
    }

    public static function postalCodeMatches(array $allowed, ?string $postalCode): bool
    {
        if ($allowed === []) {
            return true;
        }

        $code = strtoupper(preg_replace('/\s+/', '', (string) $postalCode) ?? '');
        if ($code === '') {
            return false;
        }

        foreach ($allowed as $entry) {
            if ($code === $entry || str_starts_with($code, $entry.'-') || substr($code, 0, 5) === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    private static function cleanList(array $items, int $maxLength, int $maxItems): array
    {
        $out = [];
        foreach ($items as $item) {
            $text = trim(mb_substr((string) $item, 0, $maxLength));
            if ($text !== '') {
                $out[mb_strtolower($text)] = $text;
            }
        }

        return array_slice(array_values($out), 0, $maxItems);
    }

    private static function text(mixed $value, int $max, string $fallback, bool $allowEmpty = false): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $value = trim(mb_substr($value, 0, $max));

        return $value === '' && ! $allowEmpty ? $fallback : $value;
    }

    private static function positiveOrNull(mixed $value, int $max): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int < 1 ? null : min($max, $int);
    }
}
