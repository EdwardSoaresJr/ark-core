<?php

namespace App\Ark\Operations\Learn;

final class ArkademyUrls
{
    public static function homeUrl(): string
    {
        return route('operations.learn.index');
    }

    public static function pageUrl(string $roleKey, string $slug): string
    {
        return route('operations.learn.show', [
            'role' => $roleKey,
            'article' => $slug,
        ]);
    }

    public static function pageUrlOrHome(string $roleKey, string $slug): string
    {
        return self::pageUrl($roleKey, $slug);
    }

    public static function staffNavUrl(): string
    {
        return self::homeUrl();
    }
}
