<?php

namespace App\Ark\Operations\ShopExcellence;

use App\Ark\Operations\Settings\ShopSettings;

final class ShopExcellenceTargets
{
    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'posted_labor_rate_cents' => null,
        'effective_labor_rate_floor_cents' => null,
        'parts_margin_target_percent' => 55,
        'aro_target_cents' => 75000,
        'labor_sales_target_percent' => 55,
        'parts_sales_target_percent' => 45,
        'review_cadence' => 'daily',
        'owner_digest_enabled' => true,
        'owner_digest_time' => '18:00',
        'coaching_digest_enabled' => false,
        'coaching_digest_time' => '19:00',
        'coaching_digest_recipient_emails' => [],
        'coaching_digest_extra_emails' => [],
        'last_target_review' => null,
        'monthly_fixed_costs_cents' => null,
        'net_profit_target_percent' => 20,
        'income_tax_reserve_percent' => 25,
        'payroll_tax_reserve_percent' => 10,
        'monthly_payroll_tax_cents' => null,
    ];

    /**
     * @return array{
     *     posted_labor_rate_cents: int|null,
     *     effective_labor_rate_floor_cents: int|null,
     *     parts_margin_target_percent: int,
     *     aro_target_cents: int,
     *     labor_sales_target_percent: int,
     *     parts_sales_target_percent: int,
     *     review_cadence: string,
     *     owner_digest_enabled: bool,
     *     owner_digest_time: string,
     *     coaching_digest_enabled: bool,
     *     coaching_digest_time: string,
     *     coaching_digest_recipient_emails: list<string>,
     *     coaching_digest_extra_emails: list<string>,
     *     monthly_fixed_costs_cents: int|null,
     *     net_profit_target_percent: int,
     *     income_tax_reserve_percent: int,
     *     payroll_tax_reserve_percent: int,
     *     monthly_payroll_tax_cents: int|null
     * }
     */
    public static function current(): array
    {
        $parsed = self::storedRaw();

        $settings = ShopSettings::current();
        $postedFromSettings = (int) ($settings->default_labor_rate_cents ?? 0);

        return [
            'posted_labor_rate_cents' => self::nullableInt($parsed['posted_labor_rate_cents'] ?? null)
                ?? ($postedFromSettings > 0 ? $postedFromSettings : null),
            'effective_labor_rate_floor_cents' => self::nullableInt($parsed['effective_labor_rate_floor_cents'] ?? null),
            'parts_margin_target_percent' => (int) ($parsed['parts_margin_target_percent'] ?? self::DEFAULTS['parts_margin_target_percent']),
            'aro_target_cents' => (int) ($parsed['aro_target_cents'] ?? self::DEFAULTS['aro_target_cents']),
            'labor_sales_target_percent' => (int) ($parsed['labor_sales_target_percent'] ?? self::DEFAULTS['labor_sales_target_percent']),
            'parts_sales_target_percent' => (int) ($parsed['parts_sales_target_percent'] ?? self::DEFAULTS['parts_sales_target_percent']),
            'review_cadence' => (string) ($parsed['review_cadence'] ?? self::DEFAULTS['review_cadence']),
            'owner_digest_enabled' => (bool) ($parsed['owner_digest_enabled'] ?? self::DEFAULTS['owner_digest_enabled']),
            'owner_digest_time' => (string) ($parsed['owner_digest_time'] ?? self::DEFAULTS['owner_digest_time']),
            'coaching_digest_enabled' => (bool) ($parsed['coaching_digest_enabled'] ?? self::DEFAULTS['coaching_digest_enabled']),
            'coaching_digest_time' => (string) ($parsed['coaching_digest_time'] ?? self::DEFAULTS['coaching_digest_time']),
            'coaching_digest_recipient_emails' => self::emailList($parsed['coaching_digest_recipient_emails'] ?? self::DEFAULTS['coaching_digest_recipient_emails']),
            'coaching_digest_extra_emails' => self::emailList($parsed['coaching_digest_extra_emails'] ?? self::DEFAULTS['coaching_digest_extra_emails']),
            'monthly_fixed_costs_cents' => self::nullableInt($parsed['monthly_fixed_costs_cents'] ?? null),
            'net_profit_target_percent' => (int) ($parsed['net_profit_target_percent'] ?? self::DEFAULTS['net_profit_target_percent']),
            'income_tax_reserve_percent' => (int) ($parsed['income_tax_reserve_percent'] ?? self::DEFAULTS['income_tax_reserve_percent']),
            'payroll_tax_reserve_percent' => (int) ($parsed['payroll_tax_reserve_percent'] ?? self::DEFAULTS['payroll_tax_reserve_percent']),
            'monthly_payroll_tax_cents' => self::nullableInt($parsed['monthly_payroll_tax_cents'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function raw(): array
    {
        return self::storedRaw();
    }

    public static function monthlyFixedCostsCents(): ?int
    {
        return self::nullableInt(self::current()['monthly_fixed_costs_cents'] ?? null);
    }

    public static function ownerDigestEnabled(): bool
    {
        return (bool) (self::current()['owner_digest_enabled'] ?? self::DEFAULTS['owner_digest_enabled']);
    }

    public static function ownerDigestTime(): string
    {
        $time = self::current()['owner_digest_time'] ?? self::DEFAULTS['owner_digest_time'];

        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time : self::DEFAULTS['owner_digest_time'];
    }

    public static function coachingDigestEnabled(): bool
    {
        return (bool) (self::current()['coaching_digest_enabled'] ?? self::DEFAULTS['coaching_digest_enabled']);
    }

    public static function coachingDigestTime(): string
    {
        $time = self::current()['coaching_digest_time'] ?? self::DEFAULTS['coaching_digest_time'];

        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time : self::DEFAULTS['coaching_digest_time'];
    }

    /**
     * @return list<string>
     */
    public static function coachingDigestRecipientEmails(): array
    {
        return self::emailList(self::current()['coaching_digest_recipient_emails'] ?? self::DEFAULTS['coaching_digest_recipient_emails']);
    }

    /**
     * @return list<string>
     */
    public static function coachingDigestExtraEmails(): array
    {
        return self::emailList(self::current()['coaching_digest_extra_emails'] ?? self::DEFAULTS['coaching_digest_extra_emails']);
    }

    public static function lastTargetReview(): ?string
    {
        $value = self::storedRaw()['last_target_review'] ?? null;

        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    public static function targetReviewStale(): bool
    {
        $last = self::lastTargetReview();
        if ($last === null) {
            return true;
        }

        return \App\Ark\Operations\Settings\ShopDisplayTimezone::now()
            ->diffInDays(\Illuminate\Support\Carbon::parse($last)) > 92;
    }

    /**
     * @param  array{
     *     effective_labor_rate_floor_cents?: int|null,
     *     parts_margin_target_percent: int,
     *     aro_target_cents: int,
     *     labor_sales_target_percent: int,
     *     parts_sales_target_percent: int,
     *     review_cadence?: string,
     *     owner_digest_enabled: bool,
     *     owner_digest_time: string,
     *     coaching_digest_enabled: bool,
     *     coaching_digest_time: string,
     *     coaching_digest_recipient_emails: list<string>,
     *     coaching_digest_extra_emails: list<string>,
     *     monthly_fixed_costs_cents?: int|null,
     *     net_profit_target_percent?: int,
     *     income_tax_reserve_percent?: int,
     *     payroll_tax_reserve_percent?: int,
     *     monthly_payroll_tax_cents?: int|null,
     *     last_target_review?: string|null,
     *     posted_labor_rate_cents?: int|null
     * }  $data
     */
    public static function persist(array $data): void
    {
        $existing = self::storedRaw();

        self::writeRaw(array_merge($existing, [
            'posted_labor_rate_cents' => $data['posted_labor_rate_cents'] ?? ($existing['posted_labor_rate_cents'] ?? null),
            'effective_labor_rate_floor_cents' => $data['effective_labor_rate_floor_cents'] ?? null,
            'parts_margin_target_percent' => (int) $data['parts_margin_target_percent'],
            'aro_target_cents' => (int) $data['aro_target_cents'],
            'labor_sales_target_percent' => (int) $data['labor_sales_target_percent'],
            'parts_sales_target_percent' => (int) $data['parts_sales_target_percent'],
            'review_cadence' => (string) ($data['review_cadence'] ?? ($existing['review_cadence'] ?? self::DEFAULTS['review_cadence'])),
            'owner_digest_enabled' => (bool) $data['owner_digest_enabled'],
            'owner_digest_time' => (string) $data['owner_digest_time'],
            'coaching_digest_enabled' => (bool) ($data['coaching_digest_enabled'] ?? ($existing['coaching_digest_enabled'] ?? self::DEFAULTS['coaching_digest_enabled'])),
            'coaching_digest_time' => (string) ($data['coaching_digest_time'] ?? ($existing['coaching_digest_time'] ?? self::DEFAULTS['coaching_digest_time'])),
            'coaching_digest_recipient_emails' => self::emailList($data['coaching_digest_recipient_emails'] ?? ($existing['coaching_digest_recipient_emails'] ?? self::DEFAULTS['coaching_digest_recipient_emails'])),
            'coaching_digest_extra_emails' => self::emailList($data['coaching_digest_extra_emails'] ?? ($existing['coaching_digest_extra_emails'] ?? self::DEFAULTS['coaching_digest_extra_emails'])),
            'monthly_fixed_costs_cents' => $data['monthly_fixed_costs_cents'] ?? null,
            'net_profit_target_percent' => (int) ($data['net_profit_target_percent'] ?? ($existing['net_profit_target_percent'] ?? self::DEFAULTS['net_profit_target_percent'])),
            'income_tax_reserve_percent' => (int) ($data['income_tax_reserve_percent'] ?? ($existing['income_tax_reserve_percent'] ?? self::DEFAULTS['income_tax_reserve_percent'])),
            'payroll_tax_reserve_percent' => (int) ($data['payroll_tax_reserve_percent'] ?? ($existing['payroll_tax_reserve_percent'] ?? self::DEFAULTS['payroll_tax_reserve_percent'])),
            'monthly_payroll_tax_cents' => $data['monthly_payroll_tax_cents'] ?? ($existing['monthly_payroll_tax_cents'] ?? null),
            'last_target_review' => $data['last_target_review'] ?? ($existing['last_target_review'] ?? null),
        ]));
    }

    /**
     * @return 'good'|'warn'|null
     */
    public static function toneForMinimum(?int $actualCents, ?int $floorCents): ?string
    {
        if ($actualCents === null || $floorCents === null || $floorCents <= 0) {
            return null;
        }

        return $actualCents >= $floorCents ? 'good' : 'warn';
    }

    /**
     * @return 'good'|'warn'|null
     */
    public static function toneForMinimumPercent(?int $actualPercent, int $targetPercent): ?string
    {
        if ($actualPercent === null) {
            return null;
        }

        return $actualPercent >= $targetPercent ? 'good' : 'warn';
    }

    /**
     * @return 'good'|'warn'|null
     */
    public static function toneForMixPercent(?int $laborPercent, int $laborTargetPercent, int $tolerance = 8): ?string
    {
        if ($laborPercent === null) {
            return null;
        }

        return abs($laborPercent - $laborTargetPercent) <= $tolerance ? 'good' : 'warn';
    }

    /**
     * @return array<string, mixed>
     */
    private static function storedRaw(): array
    {
        $stored = ShopSettings::current()->shop_excellence_targets;

        return is_array($stored) && $stored !== [] ? $stored : self::DEFAULTS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function writeRaw(array $data): void
    {
        ShopSettings::current()->update([
            'shop_excellence_targets' => $data,
        ]);
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return list<string>
     */
    private static function emailList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($email): bool => is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false)
            ->map(fn (string $email): string => trim($email))
            ->unique()
            ->values()
            ->all();
    }
}
