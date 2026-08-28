<?php

namespace App\Ark\Operations\Leads\Public;

final class PublicLegacyRedirect
{
    public static function resolve(string $path): ?string
    {
        $normalized = '/'.trim($path, '/');
        if ($normalized === '/') {
            return null;
        }

        $exact = config('public_legacy_redirects.exact', []);
        if (isset($exact[$normalized])) {
            return (string) $exact[$normalized];
        }

        $concernSlug = self::extractConcernSlug($normalized);
        $concernTarget = self::resolveConcernSlug($concernSlug, $normalized);
        if ($concernTarget !== null) {
            return $concernTarget;
        }

        if (str_starts_with(ltrim($normalized, '/'), 'blog/')) {
            $blogSlug = substr(ltrim($normalized, '/'), strlen('blog/'));
            $blogTarget = self::resolveBlogSlug($blogSlug);
            if ($blogTarget !== null) {
                return $blogTarget;
            }

            return '/common-problems';
        }

        foreach (config('public_legacy_redirects.patterns', []) as $pattern) {
            if (! is_array($pattern)) {
                continue;
            }

            $match = (string) ($pattern['match'] ?? '');
            $target = (string) ($pattern['to'] ?? '');

            if ($match === '' || $target === '') {
                continue;
            }

            $relativePath = ltrim($normalized, '/');

            if (preg_match($match, $relativePath, $captures) !== 1) {
                continue;
            }

            if (str_contains($target, '{slug_concern}') && isset($captures[1])) {
                $target = str_replace(
                    '{slug_concern}',
                    rawurlencode(self::slugToConcern((string) $captures[1])),
                    $target,
                );
            }

            return $target;
        }

        return null;
    }

    public static function slugToConcern(string $slug): string
    {
        $phrase = str_replace('-', ' ', strtolower(trim($slug)));

        return ucfirst($phrase);
    }

    private static function extractConcernSlug(string $normalized): ?string
    {
        $relative = ltrim($normalized, '/');

        if (str_starts_with($relative, 'common-problems/')) {
            $slug = substr($relative, strlen('common-problems/'));
            if ($slug !== '' && ! str_contains($slug, '/')) {
                return $slug;
            }

            return null;
        }

        if (! str_contains($relative, '/')) {
            return $relative;
        }

        return null;
    }

    private static function resolveConcernSlug(?string $slug, string $normalized): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        $legacyMap = config('public_legacy_redirects.legacy_concern_slugs', []);
        if (isset($legacyMap[$slug])) {
            $target = '/common-problems/'.$legacyMap[$slug];
            if ($normalized !== $target) {
                return $target;
            }

            return null;
        }

        if (str_starts_with(ltrim($normalized, '/'), 'common-problems/')) {
            return null;
        }

        if (CommonProblemRegistry::find($slug) !== null) {
            return '/common-problems/'.$slug;
        }

        return null;
    }

    private static function resolveBlogSlug(string $blogSlug): ?string
    {
        if ($blogSlug === '') {
            return null;
        }

        $legacyMap = config('public_legacy_redirects.legacy_concern_slugs', []);
        if (isset($legacyMap[$blogSlug])) {
            return '/common-problems/'.$legacyMap[$blogSlug];
        }

        if (CommonProblemRegistry::find($blogSlug) !== null) {
            return '/common-problems/'.$blogSlug;
        }

        foreach (config('public_legacy_redirects.blog_patterns', []) as $pattern) {
            if (! is_array($pattern)) {
                continue;
            }

            $match = (string) ($pattern['match'] ?? '');
            $target = (string) ($pattern['to'] ?? '');

            if ($match === '' || $target === '') {
                continue;
            }

            if (preg_match($match, $blogSlug) === 1) {
                return $target;
            }
        }

        return null;
    }
}
