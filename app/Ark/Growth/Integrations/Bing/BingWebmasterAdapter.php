<?php

namespace App\Ark\Growth\Integrations\Bing;

use App\Ark\Growth\Integrations\Contracts\WebmasterAdapter;

final class BingWebmasterAdapter implements WebmasterAdapter
{
    public function isConfigured(): bool
    {
        return (bool) config('growth.integrations.bing_webmaster.enabled', false);
    }

    public function fetchQueries(string $startDate, string $endDate): array
    {
        return [];
    }
}
