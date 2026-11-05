<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * The shop's scheduling policy for online appointment requests (shop_settings.online_booking).
 *
 * Only what Core enforces when a booking product submits through OnlineBooking: whether online
 * customers send requests or may take an open time, the daily intake limit, capacity, concurrent
 * appointments, and the service area. Request days, windows, horizon, notice and closed dates stay in
 * AppointmentRequestAvailability. How the public form looks and asks lives in the booking product.
 * Every read normalizes, so a missing or partial value behaves like the defaults.
 */
final class BookingSettings
{
    /** The visit lasts as long as its longest item; the vehicle stays while the work is done. */
    public const VISIT_DROP_OFF = 'drop_off';

    /** The items' times add up: the customer waits, or a mobile technician works through the list. */
    public const VISIT_SEQUENTIAL = 'sequential';

    /**
     * @return array{mode: string, visit_length: string, service_area: array{postal_codes: list<string>, message: string}, limits: array{daily_requests: int|null, respect_capacity: bool, concurrent_appointments: int|null}}
     */
    public static function defaults(): array
    {
        return [
            'mode' => BookingMode::Request->value,
            'visit_length' => self::VISIT_DROP_OFF,
            'service_area' => [
                'postal_codes' => [],
                'message' => '',
            ],
            'limits' => [
                'daily_requests' => null,
                'respect_capacity' => true,
                'concurrent_appointments' => null,
            ],
        ];
    }

    /**
     * @return array{mode: string, visit_length: string, service_area: array{postal_codes: list<string>, message: string}, limits: array{daily_requests: int|null, respect_capacity: bool, concurrent_appointments: int|null}}
     */
    public static function forShop(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $stored = $settings->getAttribute('online_booking');

        return self::normalize(is_array($stored) ? $stored : null);
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array{mode: string, visit_length: string, service_area: array{postal_codes: list<string>, message: string}, limits: array{daily_requests: int|null, respect_capacity: bool, concurrent_appointments: int|null}}
     */
    public static function normalize(?array $config): array
    {
        $config ??= [];
        $areaIn = is_array($config['service_area'] ?? null) ? $config['service_area'] : [];
        $limitsIn = is_array($config['limits'] ?? null) ? $config['limits'] : [];

        return [
            'mode' => (BookingMode::tryFrom((string) ($config['mode'] ?? '')) ?? BookingMode::Request)->value,
            'visit_length' => ($config['visit_length'] ?? null) === self::VISIT_SEQUENTIAL ? self::VISIT_SEQUENTIAL : self::VISIT_DROP_OFF,
            'service_area' => [
                'postal_codes' => self::postalCodes($areaIn['postal_codes'] ?? []),
                'message' => self::text($areaIn['message'] ?? null, 300),
            ],
            'limits' => [
                'daily_requests' => self::positiveOrNull($limitsIn['daily_requests'] ?? null, 500),
                'respect_capacity' => array_key_exists('respect_capacity', $limitsIn)
                    ? filter_var($limitsIn['respect_capacity'], FILTER_VALIDATE_BOOL)
                    : true,
                'concurrent_appointments' => self::positiveOrNull($limitsIn['concurrent_appointments'] ?? null, 50),
            ],
        ];
    }

    /**
     * Replace the keys given, keep the rest, and store the normalized policy.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function save(array $changes): void
    {
        ShopSettings::current()->forceFill(['online_booking' => self::normalize([...self::forShop(), ...$changes])])->save();
        ShopSettings::forgetCurrent();
    }

    public static function mode(?ShopSettings $settings = null): BookingMode
    {
        return BookingMode::from(self::forShop($settings)['mode']);
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

    private static function text(mixed $value, int $max): string
    {
        return is_string($value) ? trim(mb_substr($value, 0, $max)) : '';
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
