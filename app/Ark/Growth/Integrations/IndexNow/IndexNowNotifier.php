<?php

namespace App\Ark\Growth\Integrations\IndexNow;

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifies Bing, Yandex, Seznam, and Naver via the IndexNow protocol.
 */
final class IndexNowNotifier
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function isConfigured(): bool
    {
        return GrowthIntegrationSettings::current()->indexNowKey() !== null;
    }

    /**
     * @param  list<string>  $absoluteUrls
     * @return array{submitted: bool, status: int|null, hosts: list<string>, message: string}
     */
    public function submitUrls(array $absoluteUrls): array
    {
        $settings = GrowthIntegrationSettings::current();
        $key = $settings->indexNowKey();

        if ($key === null || $absoluteUrls === []) {
            return [
                'submitted' => false,
                'status' => null,
                'hosts' => [],
                'message' => 'IndexNow not configured or no URLs provided.',
            ];
        }

        $host = parse_url(PublicMarketingUrl::baseUrl(), PHP_URL_HOST) ?: '';
        $keyLocation = PublicMarketingUrl::baseUrl().'/indexnow-'.$key.'.txt';

        $response = Http::timeout(20)->post(self::ENDPOINT, [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => array_values(array_unique($absoluteUrls)),
        ]);

        if ($response->successful() || $response->status() === 202) {
            return [
                'submitted' => true,
                'status' => $response->status(),
                'hosts' => ['bing', 'yandex', 'seznam', 'naver'],
                'message' => 'IndexNow accepted '.count($absoluteUrls).' URL(s).',
            ];
        }

        Log::warning('IndexNow submission failed.', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'submitted' => false,
            'status' => $response->status(),
            'hosts' => [],
            'message' => 'IndexNow failed: '.$response->status(),
        ];
    }

    public function ensureKey(): string
    {
        $settings = GrowthIntegrationSettings::current();
        $existing = $settings->indexNowKey();

        if ($existing !== null) {
            return $existing;
        }

        $key = Str::lower(Str::random(32));
        $settings->persistIndexNowKey($key);

        return $key;
    }
}
