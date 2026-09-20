<?php

namespace App\Ark\Operations\Settings;

use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class ShopDisplayTimezone
{
    public static function apply(): void
    {
        if (! self::schemaReady()) {
            return;
        }

        config(['app.display_timezone' => self::resolve()]);
    }

    public static function resolve(): string
    {
        if (! self::schemaReady()) {
            throw new RuntimeException('Shop timezone is unavailable before shop settings are migrated.');
        }

        $timezone = trim((string) (ShopSettings::current()->shop_timezone ?? ''));

        if ($timezone === '') {
            throw new RuntimeException('Shop timezone is required. Configure it in Settings → Shop.');
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException("Shop timezone [{$timezone}] is not valid.");
        }

        return $timezone;
    }

    public static function format(?CarbonInterface $instant, string $format = 'M j, Y g:i A'): ?string
    {
        if ($instant === null) {
            return null;
        }

        return $instant->copy()
            ->utc()
            ->timezone(self::resolve())
            ->format($format);
    }

    public static function formatDate(?CarbonInterface $instant): ?string
    {
        return self::format($instant, 'M j, Y');
    }

    /**
     * Parse a shop-local wall time (datetime-local / schedule slots) into an absolute instant.
     */
    public static function parseLocal(string $value): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($value, self::resolve());
    }

    /**
     * Present an absolute instant in the shop timezone.
     */
    public static function present(CarbonInterface $instant): \Illuminate\Support\Carbon
    {
        return $instant->copy()->utc()->timezone(self::resolve());
    }

    public static function now(): \Illuminate\Support\Carbon
    {
        return now('UTC')->timezone(self::resolve());
    }

    private static function schemaReady(): bool
    {
        try {
            return Schema::hasTable('shop_settings')
                && Schema::hasColumn('shop_settings', 'shop_timezone');
        } catch (Throwable) {
            return false;
        }
    }
}
