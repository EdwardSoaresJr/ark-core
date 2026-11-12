<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\ShopSettings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shop-level catalog of named tax profiles and components (settings only).
 * RO stamps remain TaxProfile schema 1 in Slice A — projected from the default profile.
 */
final class ShopTaxCatalog
{
    public const CATALOG_SCHEMA = 1;

    public const MAX_PROFILES = 25;

    public const MAX_COMPONENTS_PER_PROFILE = 12;

    public const PRESENTATION_COMBINED = 'combined';

    public const PRESENTATION_ITEMIZED = 'itemized';

    /** @var list<string> */
    public const JURISDICTION_TYPES = [
        'state',
        'county',
        'city',
        'special_district',
        'other',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function profilesFromShopSettings(ShopSettings $settings): array
    {
        $stored = $settings->tax_profiles;

        if (is_array($stored) && $stored !== []) {
            return self::normalizeProfiles($stored, preserveInactiveComponents: true);
        }

        return [self::legacySingleProfile($settings)];
    }

    public static function documentPresentation(ShopSettings $settings): string
    {
        $value = (string) ($settings->tax_document_presentation ?? self::PRESENTATION_COMBINED);

        return in_array($value, [self::PRESENTATION_COMBINED, self::PRESENTATION_ITEMIZED], true)
            ? $value
            : self::PRESENTATION_COMBINED;
    }

    /**
     * @param  list<array<string, mixed>>  $profiles
     * @return array{
     *     tax_label: string,
     *     default_tax_rate: string,
     *     taxable_labor: bool,
     *     taxable_parts: bool,
     *     taxable_shop_fees: bool
     * }
     */
    public static function projectLegacyColumns(array $profiles): array
    {
        $default = collect($profiles)->first(
            fn (array $profile): bool => (bool) ($profile['is_default'] ?? false) && (bool) ($profile['is_active'] ?? true),
        ) ?? collect($profiles)->first(
            fn (array $profile): bool => (bool) ($profile['is_active'] ?? true),
        ) ?? ($profiles[0] ?? null);

        if (! is_array($default)) {
            return [
                'tax_label' => 'Tax',
                'default_tax_rate' => '0.000',
                'taxable_labor' => false,
                'taxable_parts' => true,
                'taxable_shop_fees' => false,
            ];
        }

        return [
            'tax_label' => (string) ($default['name'] ?? 'Tax'),
            'default_tax_rate' => self::combinedRate($default),
            'taxable_labor' => (bool) ($default['taxable_labor'] ?? false),
            'taxable_parts' => (bool) ($default['taxable_parts'] ?? true),
            'taxable_shop_fees' => (bool) ($default['taxable_shop_fees'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public static function combinedRate(array $profile): string
    {
        $total = BigDecimal::zero();

        foreach ($profile['components'] ?? [] as $component) {
            if (! is_array($component) || ! ($component['is_active'] ?? true)) {
                continue;
            }

            $total = $total->plus(BigDecimal::of((string) ($component['rate'] ?? '0')));
        }

        return $total->toScale(3, RoundingMode::HALF_UP)->__toString();
    }

    /**
     * @param  list<array<string, mixed>>|array<int, array<string, mixed>>  $input
     * @return list<array<string, mixed>>
     */
    public static function normalizeProfiles(array $input, bool $preserveInactiveComponents = true): array
    {
        if (count($input) > self::MAX_PROFILES) {
            throw ValidationException::withMessages([
                'tax_profiles' => 'Keep tax profiles to '.self::MAX_PROFILES.' or fewer.',
            ]);
        }

        if ($input === []) {
            throw ValidationException::withMessages([
                'tax_profiles' => 'Add at least one tax profile.',
            ]);
        }

        $seenKeys = [];
        $profiles = [];
        $defaultKey = null;

        foreach (array_values($input) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $key = self::normalizeKey((string) ($raw['key'] ?? ''), 'profile-'.($index + 1));
            if (isset($seenKeys[$key])) {
                $key = $key.'-'.($index + 1);
            }
            $seenKeys[$key] = true;

            $name = trim((string) ($raw['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    "tax_profiles.{$index}.name" => 'Enter a tax profile name.',
                ]);
            }

            $isActive = filter_var($raw['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $isDefault = filter_var($raw['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isDefault && $isActive) {
                $defaultKey = $key;
            }

            $components = self::normalizeComponents(
                is_array($raw['components'] ?? null) ? $raw['components'] : [],
                $index,
                $preserveInactiveComponents,
            );

            if ($isActive && $components === []) {
                throw ValidationException::withMessages([
                    "tax_profiles.{$index}.components" => 'Each active tax profile needs at least one tax component.',
                ]);
            }

            $profiles[] = [
                'key' => $key,
                'name' => Str::limit($name, 64, ''),
                'is_default' => false,
                'is_active' => $isActive,
                'state' => self::nullableTrimmedString($raw['state'] ?? null, 2),
                'cities' => self::normalizeStringList($raw['cities'] ?? [], 32),
                'zip_codes' => self::normalizeZipList($raw['zip_codes'] ?? []),
                'service_area_description' => self::nullableTrimmedString($raw['service_area_description'] ?? null, 255),
                'reporting_code' => self::nullableTrimmedString($raw['reporting_code'] ?? null, 64),
                'taxable_labor' => filter_var($raw['taxable_labor'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'taxable_parts' => filter_var($raw['taxable_parts'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'taxable_shop_fees' => filter_var($raw['taxable_shop_fees'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'components' => $components,
                'catalog_schema' => self::CATALOG_SCHEMA,
            ];
        }

        if ($profiles === []) {
            throw ValidationException::withMessages([
                'tax_profiles' => 'Add at least one tax profile.',
            ]);
        }

        if ($defaultKey === null) {
            foreach ($profiles as $profile) {
                if ($profile['is_active']) {
                    $defaultKey = $profile['key'];
                    break;
                }
            }
            $defaultKey ??= $profiles[0]['key'];
        }

        foreach ($profiles as &$profile) {
            $profile['is_default'] = $profile['key'] === $defaultKey;
            if ($profile['is_default']) {
                $profile['is_active'] = true;
            }
        }
        unset($profile);

        return array_values($profiles);
    }

    /**
     * @return array<string, mixed>
     */
    public static function legacySingleProfile(ShopSettings $settings): array
    {
        $label = filled($settings->tax_label) ? (string) $settings->tax_label : 'Sales Tax';
        $rate = filled($settings->default_tax_rate) ? (string) $settings->default_tax_rate : '0';

        return [
            'key' => 'shop-default',
            'name' => $label,
            'is_default' => true,
            'is_active' => true,
            'state' => null,
            'cities' => [],
            'zip_codes' => [],
            'service_area_description' => null,
            'reporting_code' => null,
            'taxable_labor' => (bool) $settings->taxable_labor,
            'taxable_parts' => (bool) $settings->taxable_parts,
            'taxable_shop_fees' => (bool) $settings->taxable_shop_fees,
            'components' => [[
                'id' => 'legacy-combined',
                'name' => $label,
                'rate' => BigDecimal::of($rate)->toScale(3, RoundingMode::HALF_UP)->__toString(),
                'jurisdiction_type' => 'other',
                'reporting_code' => null,
                'sort_order' => 1,
                'is_active' => true,
            ]],
            'catalog_schema' => self::CATALOG_SCHEMA,
        ];
    }

    /**
     * Build catalog profiles from the pre-multi-profile tax.update payload.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function profilesFromLegacyRequest(array $data, ShopSettings $settings): array
    {
        $existing = self::profilesFromShopSettings($settings);
        $default = collect($existing)->firstWhere('is_default', true) ?? $existing[0];
        $label = trim((string) ($data['tax_label'] ?? $default['name'] ?? 'Tax')) ?: 'Tax';
        $rate = BigDecimal::of((string) ($data['default_tax_rate'] ?? '0'))
            ->toScale(3, RoundingMode::HALF_UP)
            ->__toString();

        $components = $default['components'] ?? [];
        if (count($components) === 1) {
            $components[0]['name'] = $label;
            $components[0]['rate'] = $rate;
            $components[0]['is_active'] = true;
        } else {
            $components = [[
                'id' => (string) ($components[0]['id'] ?? 'legacy-combined'),
                'name' => $label,
                'rate' => $rate,
                'jurisdiction_type' => 'other',
                'reporting_code' => null,
                'sort_order' => 1,
                'is_active' => true,
            ]];
        }

        $default['name'] = $label;
        $default['taxable_labor'] = (bool) ($data['taxable_labor'] ?? false);
        $default['taxable_parts'] = (bool) ($data['taxable_parts'] ?? false);
        $default['taxable_shop_fees'] = (bool) ($data['taxable_shop_fees'] ?? false);
        $default['components'] = $components;
        $default['is_default'] = true;
        $default['is_active'] = true;

        $others = collect($existing)
            ->reject(fn (array $profile): bool => $profile['key'] === $default['key'])
            ->values()
            ->all();

        return self::normalizeProfiles([$default, ...$others]);
    }

    /**
     * @param  list<array<string, mixed>>  $input
     * @return list<array<string, mixed>>
     */
    private static function normalizeComponents(array $input, int $profileIndex, bool $preserveInactive): array
    {
        if (count($input) > self::MAX_COMPONENTS_PER_PROFILE) {
            throw ValidationException::withMessages([
                "tax_profiles.{$profileIndex}.components" => 'Keep tax components to '.self::MAX_COMPONENTS_PER_PROFILE.' or fewer per profile.',
            ]);
        }

        $seenIds = [];
        $components = [];

        foreach (array_values($input) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $isActive = filter_var($raw['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if (! $preserveInactive && ! $isActive) {
                continue;
            }

            $id = trim((string) ($raw['id'] ?? ''));
            if ($id === '' || isset($seenIds[$id])) {
                $id = (string) Str::uuid();
            }
            $seenIds[$id] = true;

            $name = trim((string) ($raw['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    "tax_profiles.{$profileIndex}.components.{$index}.name" => 'Enter a tax component name.',
                ]);
            }

            $type = (string) ($raw['jurisdiction_type'] ?? 'other');
            if (! in_array($type, self::JURISDICTION_TYPES, true)) {
                $type = 'other';
            }

            try {
                $rate = BigDecimal::of((string) ($raw['rate'] ?? '0'));
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    "tax_profiles.{$profileIndex}.components.{$index}.rate" => 'Enter a valid tax component rate.',
                ]);
            }

            if ($rate->isNegative() || $rate->isGreaterThan(100)) {
                throw ValidationException::withMessages([
                    "tax_profiles.{$profileIndex}.components.{$index}.rate" => 'Tax component rates must be between 0 and 100.',
                ]);
            }

            $components[] = [
                'id' => $id,
                'name' => Str::limit($name, 64, ''),
                'rate' => $rate->toScale(3, RoundingMode::HALF_UP)->__toString(),
                'jurisdiction_type' => $type,
                'reporting_code' => self::nullableTrimmedString($raw['reporting_code'] ?? null, 64),
                'sort_order' => $index + 1,
                'is_active' => $isActive,
            ];
        }

        return $components;
    }

    private static function normalizeKey(string $key, string $fallback): string
    {
        $key = Str::slug($key);
        if ($key === '') {
            $key = Str::slug($fallback);
        }

        return $key !== '' ? $key : 'tax-profile';
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private static function normalizeStringList(mixed $value, int $maxItems): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\n,]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->unique()
            ->take($maxItems)
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private static function normalizeZipList(mixed $value): array
    {
        return collect(self::normalizeStringList($value, 64))
            ->map(function (string $zip): ?string {
                $zip = strtoupper(preg_replace('/\s+/', '', $zip) ?? '');
                if ($zip === '') {
                    return null;
                }

                return Str::limit($zip, 16, '');
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function nullableTrimmedString(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : Str::limit($trimmed, $max, '');
    }
}
