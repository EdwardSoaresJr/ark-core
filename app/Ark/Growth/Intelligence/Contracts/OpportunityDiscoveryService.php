<?php

namespace App\Ark\Growth\Intelligence\Contracts;

interface OpportunityDiscoveryService
{
    /**
     * @return list<array{title: string, rationale: string, evidence: array<string, mixed>}>
     */
    public function discover(): array;
}
