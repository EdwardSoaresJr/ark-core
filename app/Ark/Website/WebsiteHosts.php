<?php

namespace App\Ark\Website;

/**
 * Native ARK site host, custom domain, and preferred public host.
 *
 * website_sites.public_host is the native site host. A custom domain is an
 * explicit mapping onto that host. SEO canonical uses the custom domain only
 * when that mapping is marked preferred.
 */
final class WebsiteHosts
{
    /**
     * @return list<array{domain: string, site_host: string, preferred: bool}>
     */
    public static function customDomains(): array
    {
        $entries = [];

        foreach ((array) config('website.custom_domains', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $domain = self::host((string) ($entry['domain'] ?? ''));
            $siteHost = self::host((string) ($entry['site_host'] ?? ''));
            if ($domain === null || $siteHost === null || $domain === $siteHost || str_starts_with($domain, 'www.')) {
                continue;
            }

            $entries[] = [
                'domain' => $domain,
                'site_host' => $siteHost,
                'preferred' => (bool) ($entry['preferred'] ?? false),
            ];
        }

        return $entries;
    }

    public static function isCustomDomain(string $host): bool
    {
        $host = self::host($host);
        if ($host === null) {
            return false;
        }

        foreach (self::customDomains() as $entry) {
            if ($entry['domain'] === $host) {
                return true;
            }
        }

        return false;
    }

    public static function nativeHostForCustomDomain(string $host): ?string
    {
        $host = self::host($host);
        if ($host === null) {
            return null;
        }

        $targets = [];
        foreach (self::customDomains() as $entry) {
            if ($entry['domain'] === $host) {
                $targets[$entry['site_host']] = true;
            }
        }

        if (count($targets) !== 1) {
            return null;
        }

        return (string) array_key_first($targets);
    }

    public static function preferredPublicHost(string $nativeHost): string
    {
        $nativeHost = self::host($nativeHost) ?? '';
        $preferred = [];

        foreach (self::customDomains() as $entry) {
            if ($entry['site_host'] === $nativeHost && $entry['preferred']) {
                $preferred[$entry['domain']] = true;
            }
        }

        if (count($preferred) === 1) {
            return (string) array_key_first($preferred);
        }

        return $nativeHost;
    }

    public static function wwwApex(string $host): ?string
    {
        $host = self::host($host);
        if ($host === null || ! str_starts_with($host, 'www.')) {
            return null;
        }

        $apex = substr($host, 4);
        if (self::nativeHostForCustomDomain($apex) === null) {
            return null;
        }

        return $apex;
    }

    public static function host(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '' || str_contains($host, '/') || str_contains($host, ' ') || str_contains($host, '..')) {
            return null;
        }

        return $host;
    }
}
