<?php

namespace App\Ark\Website\Projections;

use App\Ark\Growth\Maintenance\GrowthSyncTaskKey;
use App\Ark\Growth\Maintenance\GrowthSyncTaskRecorder;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Models\GrowthSyncTask;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;

final class WebsiteHealthProjection
{
    public function __construct(
        private readonly GrowthSyncTaskRecorder $syncTasks,
    ) {}

    /**
     * @return list<array{label: string, ready: bool, hint: string|null}>
     */
    public function resolve(): array
    {
        $public = PublicSurfaceSettings::current();
        $photosUploaded = collect($public['shop_photos'] ?? [])
            ->filter(fn (array $photo): bool => filled($photo['path'] ?? null))
            ->count();

        $searchConsole = $this->syncTasks->lastRun(GrowthSyncTaskKey::SearchConsole);
        $gbp = $this->syncTasks->lastRun(GrowthSyncTaskKey::GoogleBusinessProfile);

        return [
            [
                'label' => 'Homepage complete',
                'ready' => filled($public['headline'] ?? null)
                    && $photosUploaded >= 2
                    && (int) ($public['google_review_count'] ?? 0) > 0,
                'hint' => 'Headline, at least two proof photos, and Google review count.',
            ],
            [
                'label' => 'SEO configured',
                'ready' => CommonProblemRegistry::all() !== []
                    || GrowthContent::query()->exists(),
                'hint' => 'Common problem pages or Growth content registry entries exist.',
            ],
            [
                'label' => 'Sitemap submitted',
                'ready' => GrowthIntegrationSettings::current()->isPublicSitemapEnabled(),
                'hint' => GrowthIntegrationSettings::current()->isPublicSitemapEnabled()
                    ? 'Public sitemap is live from Growth.'
                    : 'Enable under Growth → Integrations after submitting the sitemap in Search Console.',
            ],
            [
                'label' => 'Search Console connected',
                'ready' => $this->integrationHealthy($searchConsole)
                    || $this->searchConsoleConfigured(),
                'hint' => null,
            ],
            [
                'label' => 'Google Business Profile connected',
                'ready' => $this->integrationHealthy($gbp)
                    || GrowthIntegrationSettings::current()->isGoogleBusinessProfileConfigured(),
                'hint' => null,
            ],
        ];
    }

    private function searchConsoleConfigured(): bool
    {
        $integration = config('growth.integrations.google_search_console', []);

        return (bool) ($integration['enabled'] ?? false)
            && filled($integration['property'] ?? null)
            && filled($integration['credentials_json'] ?? null);
    }

    private function integrationHealthy(?GrowthSyncTask $task): bool
    {
        return $task !== null && $task->isHealthy() && $task->last_ran_at !== null;
    }
}
