<?php

namespace App\Ark\Growth\Integrations\Contracts;

interface AnalyticsAdapter
{
    public function isConfigured(): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchPageMetrics(string $startDate, string $endDate): array;
}
