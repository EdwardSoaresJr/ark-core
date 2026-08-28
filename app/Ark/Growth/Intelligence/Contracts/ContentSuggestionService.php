<?php

namespace App\Ark\Growth\Intelligence\Contracts;

interface ContentSuggestionService
{
    /**
     * @return list<array{topic: string, rationale: string, demand_signals: array<string, mixed>}>
     */
    public function suggest(): array;
}
