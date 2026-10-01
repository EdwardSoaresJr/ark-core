<?php

namespace App\Ark\Operations\Diagnostics;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class OperationalClockProjection
{
    public function __construct(
        public readonly CarbonInterface $serverUtc,
        public readonly string $phpDefaultTimezone,
        public readonly bool $phpIsUtc,
        public readonly ?CarbonInterface $dbUtc,
        public readonly ?CarbonInterface $dbSessionNow,
        public readonly bool $dbMatchesUtc,
        public readonly ?string $dbSessionTimezone,
        public readonly ?string $dbGlobalTimezone,
        public readonly string $shopTimezone,
        public readonly string $shopShortLabel,
        public readonly string $shopAbbreviation,
    ) {}

    public static function resolve(): self
    {
        $serverUtc = now('UTC');
        $phpDefaultTimezone = date_default_timezone_get();
        $shopTimezone = ShopDisplayTimezone::resolve();
        $shopNow = $serverUtc->copy()->timezone($shopTimezone);

        [$dbUtc, $dbSessionNow, $dbSessionTimezone, $dbGlobalTimezone] = self::databaseClock();

        $dbMatchesUtc = $dbUtc !== null
            && $dbSessionNow !== null
            && $dbUtc->equalTo($dbSessionNow);

        return new self(
            serverUtc: $serverUtc,
            phpDefaultTimezone: $phpDefaultTimezone,
            phpIsUtc: strtoupper($phpDefaultTimezone) === 'UTC',
            dbUtc: $dbUtc,
            dbSessionNow: $dbSessionNow,
            dbMatchesUtc: $dbMatchesUtc,
            dbSessionTimezone: $dbSessionTimezone,
            dbGlobalTimezone: $dbGlobalTimezone,
            shopTimezone: $shopTimezone,
            shopShortLabel: self::shortTimezoneLabel($shopTimezone),
            shopAbbreviation: $shopNow->format('T'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'server_utc_iso' => $this->serverUtc->toIso8601String(),
            'db_utc_iso' => $this->dbUtc?->toIso8601String(),
            'db_session_now_iso' => $this->dbSessionNow?->toIso8601String(),
            'db_available' => $this->dbUtc !== null,
            'db_matches_utc' => $this->dbMatchesUtc,
            'db_session_timezone' => $this->dbSessionTimezone,
            'db_global_timezone' => $this->dbGlobalTimezone,
            'php_default_timezone' => $this->phpDefaultTimezone,
            'php_is_utc' => $this->phpIsUtc,
            'shop_timezone' => $this->shopTimezone,
            'shop_short_label' => $this->shopShortLabel,
            'shop_abbreviation' => $this->shopAbbreviation,
        ];
    }

    /**
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface, 2: ?string, 3: ?string}
     */
    private static function databaseClock(): array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return [null, null, null, null];
        }

        $row = DB::selectOne(
            'SELECT UTC_TIMESTAMP() AS utc_now, NOW() AS session_now, @@session.time_zone AS session_tz, @@global.time_zone AS global_tz'
        );

        if ($row === null) {
            return [null, null, null, null];
        }

        return [
            Carbon::parse((string) $row->utc_now, 'UTC'),
            Carbon::parse((string) $row->session_now, 'UTC'),
            filled($row->session_tz) ? (string) $row->session_tz : null,
            filled($row->global_tz) ? (string) $row->global_tz : null,
        ];
    }

    private static function shortTimezoneLabel(string $timezone): string
    {
        $segment = strrchr($timezone, '/');

        return $segment !== false ? substr($segment, 1) : $timezone;
    }
}
