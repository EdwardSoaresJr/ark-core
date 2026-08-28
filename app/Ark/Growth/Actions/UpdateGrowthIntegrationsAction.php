<?php

namespace App\Ark\Growth\Actions;

use App\Ark\Growth\Jobs\RunGrowthBusinessProfileBackfillJob;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Cache;

final class UpdateGrowthIntegrationsAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(array $validated, bool $backfillAfterSave = false): ShopSettings
    {
        $shop = ShopSettings::current();
        $integrations = is_array($shop->growth_integrations) ? $shop->growth_integrations : [];

        $integrations['public_sitemap'] = [
            'enabled' => (bool) ($validated['public_sitemap_enabled'] ?? false),
        ];

        $integrations['google_business_profile'] = [
            'enabled' => (bool) ($validated['google_business_profile_enabled'] ?? false),
            'location' => trim((string) ($validated['google_business_profile_location'] ?? '')),
        ];

        $integrations['google_search_console'] = [
            'enabled' => (bool) ($validated['google_search_console_enabled'] ?? false),
            'property' => trim((string) ($validated['google_search_console_property'] ?? '')),
        ];

        $integrations['google_indexing'] = [
            'enabled' => (bool) ($validated['google_indexing_enabled'] ?? false),
        ];

        $integrations['seo_automation'] = [
            'enabled' => (bool) ($validated['seo_automation_enabled'] ?? true),
        ];

        $payload = [
            'growth_integrations' => $integrations,
        ];

        if (filled($validated['growth_google_service_account_json'] ?? null)) {
            $payload['growth_google_service_account'] = (string) $validated['growth_google_service_account_json'];
            Cache::forget('growth:google:gbp:access_token');
            Cache::forget('growth:google:gsc:access_token');
            Cache::forget('growth:google:indexing:access_token');
        } elseif (($validated['use_server_firebase_service_account'] ?? false) === true) {
            $payload['growth_google_service_account'] = null;
            Cache::forget('growth:google:gbp:access_token');
            Cache::forget('growth:google:gsc:access_token');
            Cache::forget('growth:google:indexing:access_token');
        }

        $shop->update($payload);

        if ($backfillAfterSave) {
            RunGrowthBusinessProfileBackfillJob::dispatch();
        }

        return $shop->fresh();
    }
}
