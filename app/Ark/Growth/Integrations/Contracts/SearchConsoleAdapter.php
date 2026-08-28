<?php

namespace App\Ark\Growth\Integrations\Contracts;

interface SearchConsoleAdapter
{
    public function isConfigured(): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchQueries(string $startDate, string $endDate): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchLandingPages(string $startDate, string $endDate): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchIndexCoverage(): array;
}
