<?php

namespace App\Ark\Growth\Integrations\Google;

use App\Ark\Growth\Integrations\Contracts\AnalyticsAdapter;

final class GoogleAnalytics4Adapter implements AnalyticsAdapter
{
    public function isConfigured(): bool
    {
        return (bool) config('growth.integrations.google_analytics_4.enabled', false);
    }

    public function fetchPageMetrics(string $startDate, string $endDate): array
    {
        return [];
    }
}
