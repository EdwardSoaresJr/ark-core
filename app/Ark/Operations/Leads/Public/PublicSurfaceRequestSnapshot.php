<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Http\Request;

/**
 * Snapshot of request attribution data for Growth contract payloads.
 * Operations-owned — Growth consumes via PublicSurfaceActivityRecorded.
 */
final class PublicSurfaceRequestSnapshot
{
    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    public static function capture(Request $request, ?array $context = null): array
    {
        $referrer = (string) $request->headers->get('referer', '');
        $page = trim((string) ($context['page'] ?? $request->query('page', '')));

        return array_filter([
            'landing_page' => self::pathFromPage($page) ?? self::pathFromUrl($request->fullUrl()),
            'referrer' => $referrer !== '' ? $referrer : null,
            'search_query' => self::searchQueryFromReferrer($referrer) ?? $request->query('utm_term'),
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_term' => $request->query('utm_term'),
            'utm_content' => $request->query('utm_content'),
            'device' => self::deviceLabel((string) $request->userAgent()),
            'country' => $request->headers->get('CF-IPCountry'),
            'state' => $request->headers->get('CF-Region'),
            'city' => $request->headers->get('CF-IPCity'),
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public static function visitorId(Request $request): ?string
    {
        $cookie = (string) $request->cookie('ark_growth_visitor', '');

        return $cookie !== '' ? $cookie : null;
    }

    private static function pathFromPage(string $page): ?string
    {
        if ($page === '') {
            return null;
        }

        if (str_starts_with($page, '/')) {
            return $page;
        }

        if (str_contains($page, 'common-problems.')) {
            return '/common-problems/'.str_replace('common-problems.', '', $page);
        }

        if ($page === 'homepage' || $page === 'home') {
            return '/';
        }

        return '/'.$page;
    }

    private static function pathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
    }

    private static function searchQueryFromReferrer(string $referrer): ?string
    {
        if ($referrer === '') {
            return null;
        }

        $parts = parse_url($referrer);

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! str_contains($host, 'google.') && ! str_contains($host, 'bing.')) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $term = trim((string) ($query['q'] ?? $query['p'] ?? ''));

        return $term !== '' ? $term : null;
    }

    private static function deviceLabel(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }
}
