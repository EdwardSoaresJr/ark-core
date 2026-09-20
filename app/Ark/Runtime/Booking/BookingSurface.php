<?php

namespace App\Ark\Runtime\Booking;

final class BookingSurface
{
    public static function baseUrl(): string
    {
        return rtrim((string) config('booking_surface.base_url', ''), '/');
    }

    public static function isConfigured(): bool
    {
        return self::baseUrl() !== '';
    }

    public static function bookUrl(string $query = ''): string
    {
        $url = self::baseUrl().'/book';

        $query = ltrim($query, '?');

        return $query === '' ? $url : $url.'?'.$query;
    }

    /**
     * @return list<string>
     */
    public static function protectedHosts(): array
    {
        /** @var list<string> $hosts */
        $hosts = config('booking_surface.protected_hosts', []);

        return $hosts;
    }

    public static function enforce(): bool
    {
        return (bool) config('booking_surface.enforce', false);
    }

    public static function hostIsProtected(?string $host): bool
    {
        $host = strtolower(trim((string) $host));

        if ($host === '') {
            return false;
        }

        foreach (self::protectedHosts() as $protected) {
            if ($host === $protected || str_ends_with($host, '.'.$protected)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hosts this Core process claims via surface / APP_URL config.
     *
     * @return list<string>
     */
    public static function claimedHosts(): array
    {
        $candidates = [
            config('surfaces.public'),
            config('surfaces.app'),
            config('surfaces.portal'),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ];

        $hosts = [];

        foreach ($candidates as $candidate) {
            $host = strtolower(trim((string) $candidate));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return list<string>
     */
    public static function claimedProtectedHosts(): array
    {
        return array_values(array_filter(
            self::claimedHosts(),
            static fn (string $host): bool => self::hostIsProtected($host)
        ));
    }
}
