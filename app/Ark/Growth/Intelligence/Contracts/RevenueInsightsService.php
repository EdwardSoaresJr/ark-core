<?php

namespace App\Ark\Growth\Intelligence\Contracts;

interface RevenueInsightsService
{
    /**
     * @return list<array{insight: string, evidence: array<string, mixed>}>
     */
    public function insights(): array;
}
