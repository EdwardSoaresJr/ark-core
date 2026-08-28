<?php

namespace App\Ark\Operations\Learn;

final class LearnArticleKey
{
    public static function make(string $roleKey, string $slug): string
    {
        return $roleKey.':'.$slug;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function parse(string $articleKey): ?array
    {
        if (! str_contains($articleKey, ':')) {
            return null;
        }

        [$roleKey, $slug] = explode(':', $articleKey, 2);

        if ($roleKey === '' || $slug === '') {
            return null;
        }

        return [$roleKey, $slug];
    }
}
