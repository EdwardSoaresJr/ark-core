<?php

namespace App\Ark\Growth\Integrations\Contracts;

interface BusinessProfileAdapter
{
    public function isConfigured(): bool;

    /**
     * @return list<array{metric: string, value: int, report_date: string, metadata?: array<string, mixed>}>
     */
    public function fetchDailyMetrics(string $reportDate): array;

    /**
     * @return list<array{metric: string, value: int, report_date: string, metadata?: array<string, mixed>}>
     */
    public function fetchMetricsBetween(string $startDate, string $endDate): array;
}
