<?php

namespace App\Ark\Growth\Integrations\Google;

use App\Ark\Growth\Integrations\Contracts\SearchConsoleAdapter;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class GoogleSearchConsoleAdapter implements SearchConsoleAdapter
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters';

    private const API_BASE = 'https://www.googleapis.com/webmasters/v3';

    public function __construct(
        private readonly GoogleServiceAccountAccessToken $tokens,
    ) {}

    public function isConfigured(): bool
    {
        return GrowthIntegrationSettings::current()->isSearchConsoleConfigured();
    }

    public function fetchQueries(string $startDate, string $endDate): array
    {
        return $this->searchAnalytics($startDate, $endDate, ['query']);
    }

    public function fetchLandingPages(string $startDate, string $endDate): array
    {
        $rows = $this->searchAnalytics($startDate, $endDate, ['page']);
        $baseHost = parse_url(PublicMarketingUrl::baseUrl(), PHP_URL_HOST) ?: '';

        return collect($rows)
            ->map(function (array $row) use ($baseHost): ?array {
                $page = (string) ($row['page'] ?? '');
                if ($page === '') {
                    return null;
                }

                $path = parse_url($page, PHP_URL_PATH) ?: '/';
                $host = parse_url($page, PHP_URL_HOST) ?: '';

                if ($baseHost !== '' && $host !== '' && $host !== $baseHost) {
                    return null;
                }

                return [
                    'path' => $path === '' ? '/' : $path,
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => isset($row['position']) ? (float) $row['position'] : null,
                    'metadata' => [
                        'source' => 'google_search_console',
                        'page' => $page,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function fetchIndexCoverage(): array
    {
        return [];
    }

    /**
     * @return array{submitted: bool, status: int|null, message: string}
     */
    public function submitSitemap(string $sitemapUrl): array
    {
        if (! $this->isConfigured()) {
            return ['submitted' => false, 'status' => null, 'message' => 'Search Console not configured.'];
        }

        $siteUrl = $this->encodedSiteUrl();
        $feedPath = rawurlencode($sitemapUrl);

        $response = Http::withToken($this->accessToken())
            ->put(self::API_BASE.'/sites/'.$siteUrl.'/sitemaps/'.$feedPath);

        if ($response->successful() || $response->status() === 409) {
            return [
                'submitted' => true,
                'status' => $response->status(),
                'message' => 'Sitemap submitted to Google Search Console.',
            ];
        }

        Log::warning('Google Search Console sitemap submit failed.', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'submitted' => false,
            'status' => $response->status(),
            'message' => 'Sitemap submit failed: '.$response->status(),
        ];
    }

    /**
     * @param  list<string>  $dimensions
     * @return list<array<string, mixed>>
     */
    private function searchAnalytics(string $startDate, string $endDate, array $dimensions): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $response = Http::withToken($this->accessToken())
            ->post(self::API_BASE.'/sites/'.$this->encodedSiteUrl().'/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => $dimensions,
                'rowLimit' => 25000,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google Search Console searchAnalytics query failed: '.$response->status().' '.$response->body(),
            );
        }

        $rows = [];

        foreach ($response->json('rows', []) as $row) {
            $keys = $row['keys'] ?? [];
            $dimension = $dimensions[0] ?? 'query';
            $label = (string) ($keys[0] ?? '');

            $clicks = (int) ($row['clicks'] ?? 0);
            $impressions = (int) ($row['impressions'] ?? 0);

            $mapped = [
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0.0,
                'position' => isset($row['position']) ? (float) $row['position'] : null,
            ];

            if ($dimension === 'query') {
                $mapped['query'] = $label;
                $mapped['metadata'] = ['source' => 'google_search_console'];
            } else {
                $mapped['page'] = $label;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    private function accessToken(): string
    {
        $credentials = GrowthIntegrationSettings::current()->googleServiceAccountCredentials();

        if ($credentials === null) {
            throw new RuntimeException('Google service account credentials are missing.');
        }

        $token = $this->tokens->accessToken(
            $credentials,
            self::SCOPE,
            'growth:google:gsc:access_token',
        );

        if ($token === null) {
            throw new RuntimeException('Unable to obtain Google Search Console access token.');
        }

        return $token;
    }

    private function encodedSiteUrl(): string
    {
        $property = GrowthIntegrationSettings::current()->searchConsoleProperty();

        return rawurlencode((string) $property);
    }
}
