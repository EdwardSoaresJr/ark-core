<?php

namespace App\Ark\Growth\Integrations\Google;

use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Requests Google to recrawl URLs via the Indexing API.
 */
final class GoogleIndexingNotifier
{
    private const SCOPE = 'https://www.googleapis.com/auth/indexing';

    private const ENDPOINT = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

    public function __construct(
        private readonly GoogleServiceAccountAccessToken $tokens,
    ) {}

    public function isConfigured(): bool
    {
        return GrowthIntegrationSettings::current()->isGoogleIndexingConfigured();
    }

    /**
     * @param  list<string>  $absoluteUrls
     * @return array{submitted: int, failed: int, messages: list<string>}
     */
    public function notifyUpdated(array $absoluteUrls): array
    {
        if (! $this->isConfigured() || $absoluteUrls === []) {
            return ['submitted' => 0, 'failed' => 0, 'messages' => ['Google Indexing API not configured.']];
        }

        $token = $this->accessToken();
        $submitted = 0;
        $failed = 0;
        $messages = [];
        $limit = (int) config('growth.seo_automation.google_indexing_daily_limit', 20);

        foreach (array_slice(array_values(array_unique($absoluteUrls)), 0, $limit) as $url) {
            $response = Http::withToken($token)->post(self::ENDPOINT, [
                'url' => $url,
                'type' => 'URL_UPDATED',
            ]);

            if ($response->successful() || $response->status() === 429) {
                $submitted++;

                if ($response->status() === 429) {
                    $messages[] = 'Google Indexing API quota reached after '.$submitted.' URL(s).';

                    break;
                }

                continue;
            }

            $failed++;
            Log::warning('Google Indexing API notification failed.', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        if ($submitted > 0) {
            $messages[] = "Google Indexing API queued {$submitted} URL(s).";
        }

        return compact('submitted', 'failed', 'messages');
    }

    private function accessToken(): string
    {
        $credentials = GrowthIntegrationSettings::current()->googleServiceAccountCredentials();

        if ($credentials === null) {
            throw new \RuntimeException('Google service account credentials are missing.');
        }

        $token = $this->tokens->accessToken(
            $credentials,
            self::SCOPE,
            'growth:google:indexing:access_token',
        );

        if ($token === null) {
            throw new \RuntimeException('Unable to obtain Google Indexing API access token.');
        }

        return $token;
    }
}
