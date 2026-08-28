<?php

namespace App\Ark\Growth\Intelligence\Contracts;

interface SearchTrendDetectionService
{
    /**
     * @return list<array{query: string, trend: string, evidence: array<string, mixed>}>
     */
    public function detect(): array;
}
