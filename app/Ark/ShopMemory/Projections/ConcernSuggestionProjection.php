<?php

namespace App\Ark\ShopMemory\Projections;

use App\Ark\ShopMemory\Suggestion\SuggestionCorpus;
use App\Ark\ShopMemory\Suggestion\SuggestionContext;
use App\Ark\ShopMemory\Suggestion\SuggestionEngine;
use App\Ark\ShopMemory\Suggestion\SuggestionResult;

/**
 * Concern surfaces ask for problem-language suggestions.
 * Does not write RepairOrderConcern. Advisor acceptance is authorship.
 */
final class ConcernSuggestionProjection
{
    public function __construct(
        private readonly SuggestionEngine $engine,
        private readonly SuggestionPresentation $presentation = new SuggestionPresentation,
    ) {}

    public function suggest(string $query, int $limit = 8, ?int $repairOrderId = null): SuggestionResult
    {
        return $this->engine->suggest(new SuggestionContext(
            query: $query,
            corpus: SuggestionCorpus::ProblemLanguage,
            limit: $limit,
            repairOrderId: $repairOrderId,
        ));
    }

    /**
     * @return list<PresentedSuggestion>
     */
    public function present(string $query, int $limit = 8, ?int $repairOrderId = null): array
    {
        return $this->presentation->presentMany(
            $this->suggest($query, $limit, $repairOrderId),
        );
    }
}
