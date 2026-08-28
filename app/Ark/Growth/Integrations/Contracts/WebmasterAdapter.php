<?php

namespace App\Ark\Growth\Integrations\Contracts;

interface WebmasterAdapter
{
    public function isConfigured(): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchQueries(string $startDate, string $endDate): array;
}
