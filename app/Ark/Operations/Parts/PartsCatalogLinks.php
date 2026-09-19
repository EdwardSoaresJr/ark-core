<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PartsCatalogLinks
{
    public const KEY_MAX = 32;

    public const LABEL_MAX = 64;

    public const MODE_CATALOG = 'catalog';

    public const MODE_LINK = 'link';

    /**
     * @return list<string>
     */
    public static function reservedKeys(): array
    {
        return array_map(
            static fn (PartsCatalogProvider $provider): string => $provider->value,
            PartsCatalogProvider::cases(),
        );
    }

    /**
     * @return list<array{key: string, label: string, url: string|null, mode: string, color: string}>
     */
    public static function stored(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $links = [];
        $taken = self::reservedKeys();

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized = self::normalizeRow($row, $taken, allowNewKey: false, allowReserved: true);

            if ($normalized === null) {
                continue;
            }

            $taken[] = $normalized['key'];
            $links[] = $normalized;
        }

        return $links;
    }

    /**
     * @return list<array{key: string, label: string, url: string|null, mode: string, color: string}>
     */
    public static function forUser(?User $user): array
    {
        if ($user === null || ! Schema::hasColumn($user->getTable(), 'parts_catalog_links')) {
            return [];
        }

        return self::stored($user->parts_catalog_links);
    }

    /**
     * @return list<array{key: string, label: string, url: string|null, mode: string, color: string}>
     */
    public static function forShop(): array
    {
        if (! Schema::hasColumn('shop_settings', 'parts_catalog_links')) {
            return [];
        }

        return self::stored(ShopSettings::current()->parts_catalog_links);
    }

    /**
     * Custom catalogs only — reserved keys are shop/platform slots.
     *
     * @return list<array{key: string, label: string, url: string|null, mode: string, color: string}>
     */
    public static function customForUser(?User $user): array
    {
        return array_values(array_filter(
            self::forUser($user),
            static fn (array $link): bool => ! in_array($link['key'], self::reservedKeys(), true),
        ));
    }

    /**
     * @return list<array{key: string, label: string, url: string|null, mode: string, color: string}>
     */
    public static function customForShop(): array
    {
        return array_values(array_filter(
            self::forShop(),
            static fn (array $link): bool => ! in_array($link['key'], self::reservedKeys(), true),
        ));
    }

    public static function urlForUser(?User $user, string $key): ?string
    {
        return self::rowForUser($user, $key)['url'] ?? null;
    }

    /**
     * @return array{key: string, label: string, url: string|null, mode: string, color: string}|null
     */
    public static function rowForUser(?User $user, string $key): ?array
    {
        foreach (self::forUser($user) as $link) {
            if ($link['key'] === $key) {
                return $link;
            }
        }

        return null;
    }

    public static function urlForShop(string $key): ?string
    {
        foreach (self::forShop() as $link) {
            if ($link['key'] === $key) {
                return $link['url'];
            }
        }

        return null;
    }

    /**
     * @param  list<array{key?: mixed, label?: mixed, url?: mixed, delete?: mixed}>  $rows
     * @return list<array{key: string, label: string, url: string|null, mode: string, color: string}>
     */
    public static function fromSubmitted(array $rows, array $existing): array
    {
        $kept = [];
        $taken = self::reservedKeys();

        foreach ($existing as $link) {
            $taken[] = $link['key'];
        }

        foreach ($rows as $row) {
            if (! is_array($row) || self::truthy($row['delete'] ?? false)) {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));
            $existingRow = $key !== ''
                ? collect($existing)->firstWhere('key', $key)
                : null;

            $normalized = self::normalizeRow([
                'key' => $existingRow['key'] ?? $key,
                'label' => $row['label'] ?? $existingRow['label'] ?? '',
                'url' => $row['url'] ?? $existingRow['url'] ?? null,
                'mode' => $row['mode'] ?? $existingRow['mode'] ?? self::MODE_LINK,
                'color' => $row['color'] ?? $existingRow['color'] ?? null,
            ], $taken, allowNewKey: $existingRow === null, allowReserved: false);

            if ($normalized === null) {
                continue;
            }

            $taken[] = $normalized['key'];
            $kept[] = $normalized;
        }

        return $kept;
    }

    /**
     * @param  list<array{key: string, label: string, url: string|null, mode?: string, color?: string}>  $links
     */
    public static function persistUser(User $user, array $links): void
    {
        if (! Schema::hasColumn($user->getTable(), 'parts_catalog_links')) {
            return;
        }

        $user->forceFill([
            'parts_catalog_links' => $links === [] ? null : array_values($links),
        ])->save();
    }

    /**
     * @param  list<array{key: string, label: string, url: string|null, mode?: string, color?: string}>  $links
     */
    public static function persistShop(array $links): void
    {
        if (! Schema::hasColumn('shop_settings', 'parts_catalog_links')) {
            return;
        }

        ShopSettings::current()->persistTrusted([
            'parts_catalog_links' => $links === [] ? null : array_values($links),
        ]);
    }

    /**
     * @param  list<array{key: string, label: string, url: string|null, mode?: string, color?: string}>  $custom
     * @return list<array{key: string, label: string, url: string|null, mode: string, color: string}>
     */
    public static function withReservedOverrides(
        array $custom,
        ?string $repairLinkUrl,
        ?string $nexpartUrl,
        ?string $repairLinkColor = null,
        ?string $nexpartColor = null,
    ): array {
        $links = [];

        if ($repairLinkUrl !== null || filled($repairLinkColor)) {
            $links[] = [
                'key' => PartsCatalogProvider::RepairLink->value,
                'label' => PartsCatalogProvider::RepairLink->label(),
                'url' => $repairLinkUrl,
                'mode' => self::MODE_LINK,
                'color' => PartsCatalogButtonColor::normalize($repairLinkColor ?: PartsCatalogButtonColor::BLUE),
            ];
        }

        if ($nexpartUrl !== null || filled($nexpartColor)) {
            $links[] = [
                'key' => PartsCatalogProvider::Nexpart->value,
                'label' => PartsCatalogProvider::Nexpart->label(),
                'url' => $nexpartUrl,
                'mode' => self::MODE_LINK,
                'color' => PartsCatalogButtonColor::normalize($nexpartColor ?: PartsCatalogButtonColor::NAVY),
            ];
        }

        foreach ($custom as $link) {
            if (in_array($link['key'], self::reservedKeys(), true)) {
                continue;
            }

            $links[] = $link;
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    public static function allowedKeys(?User $user = null): array
    {
        $keys = self::reservedKeys();

        foreach (self::customForShop() as $link) {
            $keys[] = $link['key'];
        }

        foreach (self::customForUser($user) as $link) {
            $keys[] = $link['key'];
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<string>  $taken
     * @return array{key: string, label: string, url: string|null, mode: string, color: string}|null
     */
    private static function normalizeRow(array $row, array $taken, bool $allowNewKey = true, bool $allowReserved = false): ?array
    {
        $label = trim((string) ($row['label'] ?? ''));

        if ($label === '') {
            return null;
        }

        $label = mb_substr($label, 0, self::LABEL_MAX);
        $key = trim((string) ($row['key'] ?? ''));
        $reserved = in_array($key, self::reservedKeys(), true);

        if ($key === '') {
            if (! $allowNewKey) {
                return null;
            }

            $key = self::makeKey($label, $taken);
            $reserved = false;
        }

        if ($reserved && ! $allowReserved) {
            return null;
        }

        if (strlen($key) > self::KEY_MAX) {
            return null;
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key)) {
            return null;
        }

        $url = ShopIntegrationCredentials::normalizedHttpsUrl(
            isset($row['url']) ? (string) $row['url'] : null,
        );

        $mode = strtolower(trim((string) ($row['mode'] ?? self::MODE_LINK)));

        if ($mode !== self::MODE_CATALOG) {
            $mode = self::MODE_LINK;
        }

        $color = PartsCatalogButtonColor::resolve(
            isset($row['color']) ? (string) $row['color'] : null,
            $key,
            $label,
        );

        return [
            'key' => $key,
            'label' => $label,
            'url' => $url,
            'mode' => $mode,
            'color' => $color,
        ];
    }

    /**
     * @param  list<string>  $taken
     */
    private static function makeKey(string $label, array $taken): string
    {
        $base = Str::slug($label);
        $base = $base === '' ? 'catalog' : $base;
        $base = substr($base, 0, 24);

        if (! in_array($base, $taken, true) && ! in_array($base, self::reservedKeys(), true)) {
            return $base;
        }

        for ($i = 2; $i < 100; $i++) {
            $candidate = substr($base, 0, 28).'-'.$i;

            if (! in_array($candidate, $taken, true) && ! in_array($candidate, self::reservedKeys(), true)) {
                return substr($candidate, 0, self::KEY_MAX);
            }
        }

        return substr('catalog-'.bin2hex(random_bytes(4)), 0, self::KEY_MAX);
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'on';
    }
}
