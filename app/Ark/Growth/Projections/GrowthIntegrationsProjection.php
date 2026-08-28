<?php

namespace App\Ark\Growth\Projections;

use App\Ark\Growth\Maintenance\GrowthSyncTaskKey;
use App\Ark\Growth\Maintenance\GrowthSyncTaskRecorder;
use App\Ark\Growth\Maintenance\GrowthSyncTaskStatus;
use App\Ark\Growth\Models\GrowthLocationMetric;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use App\Ark\Operations\Settings\ShopSettings;

final class GrowthIntegrationsProjection
{
    public function __construct(
        private readonly GrowthSyncTaskRecorder $syncTasks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $settings = GrowthIntegrationSettings::current();
        $shop = ShopSettings::current();
        $integrations = is_array($shop->growth_integrations) ? $shop->growth_integrations : [];
        $gbp = is_array($integrations['google_business_profile'] ?? null)
            ? $integrations['google_business_profile']
            : [];
        $gsc = is_array($integrations['google_search_console'] ?? null)
            ? $integrations['google_search_console']
            : [];
        $seoAutomation = is_array($integrations['seo_automation'] ?? null)
            ? $integrations['seo_automation']
            : [];

        $task = $this->syncTasks->lastRun(GrowthSyncTaskKey::GoogleBusinessProfile);
        $fixtureEnabled = (bool) config('growth.integrations.google_business_profile.fixture_enabled', true);

        $publicSitemap = is_array($integrations['public_sitemap'] ?? null)
            ? $integrations['public_sitemap']
            : [];

        return [
            'public_sitemap' => [
                'enabled' => filter_var($publicSitemap['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'url' => PublicMarketingUrl::baseUrl().'/sitemap.xml',
            ],
            'google_business_profile' => [
                'enabled' => filter_var($gbp['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'location' => (string) ($gbp['location'] ?? ''),
                'configured' => $settings->isGoogleBusinessProfileConfigured(),
                'has_credentials' => $settings->hasGoogleServiceAccountCredentials(),
                'credentials_source' => $settings->googleServiceAccountSource(),
                'client_email' => $this->clientEmail($settings),
                'server_firebase_client_email' => $settings->serverGoogleServiceAccountClientEmail(),
                'server_firebase_overridden' => $settings->hasGrowthGoogleServiceAccountOverride()
                    && $settings->serverGoogleServiceAccountClientEmail() !== null
                    && $settings->serverGoogleServiceAccountClientEmail() !== $this->clientEmail($settings),
                'using_fixture' => ! $settings->isGoogleBusinessProfileConfigured() && $fixtureEnabled,
            ],
            'google_search_console' => [
                'enabled' => filter_var($gsc['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'property' => (string) ($gsc['property'] ?? $settings->searchConsoleProperty() ?? ''),
                'configured' => $settings->isSearchConsoleConfigured(),
            ],
            'google_indexing' => [
                'enabled' => $settings->isGoogleIndexingEnabled(),
                'configured' => $settings->isGoogleIndexingConfigured(),
            ],
            'seo_automation' => [
                'enabled' => filter_var($seoAutomation['enabled'] ?? config('growth.seo_automation.auto_publish_enabled', true), FILTER_VALIDATE_BOOL),
                'auto_publish_max_per_night' => (int) config('growth.seo_automation.auto_publish_max_per_night', 2),
                'notify_search_engines' => filter_var(config('growth.seo_automation.notify_search_engines', true), FILTER_VALIDATE_BOOL),
            ],
            'indexnow' => [
                'configured' => $settings->indexNowKey() !== null,
                'key_url' => $settings->indexNowKey() !== null
                    ? PublicMarketingUrl::baseUrl().'/indexnow-'.$settings->indexNowKey().'.txt'
                    : null,
            ],
            'sync' => $this->presentSyncTask($task),
            'metric_days' => GrowthLocationMetric::query()->distinct()->count('report_date'),
            'metric_rows' => GrowthLocationMetric::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSyncTask(?\App\Ark\Growth\Models\GrowthSyncTask $task): array
    {
        if ($task === null) {
            return [
                'status_label' => 'Pending',
                'message' => 'Not synchronized yet.',
                'last_ran_label' => null,
            ];
        }

        $statusLabel = match ($task->status) {
            GrowthSyncTaskStatus::Success => 'Synchronized',
            GrowthSyncTaskStatus::Skipped => 'Skipped',
            GrowthSyncTaskStatus::Failed => 'Failed',
            GrowthSyncTaskStatus::Running => 'Running',
        };

        return [
            'status_label' => $statusLabel,
            'message' => (string) ($task->last_message ?? ''),
            'last_ran_label' => $task->last_ran_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
        ];
    }

    private function clientEmail(GrowthIntegrationSettings $settings): ?string
    {
        $credentials = $settings->googleServiceAccountCredentials();

        if ($credentials === null) {
            return null;
        }

        $email = trim((string) ($credentials['client_email'] ?? ''));

        return $email !== '' ? $email : null;
    }
}
