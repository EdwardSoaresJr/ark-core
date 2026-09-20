<?php

namespace App\Ark\Operations\Learn;

use App\Models\ArkademyContentRegistry;

final class ArkademyUrls
{
    public static function isCutover(): bool
    {
        return (bool) config('bookstack.cutover');
    }

    public static function homeUrl(): string
    {
        $base = rtrim((string) config('bookstack.base_url'), '/');
        $shelfSlug = (string) config('bookstack.shelf_slug', 'shop-in-a-box');

        return "{$base}/shelves/{$shelfSlug}";
    }

    public static function pageUrl(string $roleKey, string $slug): ?string
    {
        return ArkademyContentRegistry::findByLegacyKey(LearnArticleKey::make($roleKey, $slug))
            ?->bookstack_url;
    }

    public static function pageUrlOrHome(string $roleKey, string $slug): string
    {
        return self::pageUrl($roleKey, $slug) ?? self::homeUrl();
    }

    public static function staffNavUrl(): string
    {
        if (self::isCutover()) {
            return self::homeUrl();
        }

        return route('operations.learn.index');
    }
}
