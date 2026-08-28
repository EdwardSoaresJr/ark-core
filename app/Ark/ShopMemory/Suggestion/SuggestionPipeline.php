<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Provider → Normalizer → Dedupe → Rank.
 * Ranking strategy is swappable; pipeline order is frozen.
 */
final class SuggestionPipeline
{
    public function __construct(
        private readonly SuggestionDeduper $deduper = new SuggestionDeduper,
        private readonly SuggestionRanker $ranker = new SuggestionRanker,
    ) {}

    /**
     * @param  list<Suggestion>  $suggestions
     * @return list<Suggestion>
     */
    public function process(array $suggestions, SuggestionContext $context): array
    {
        return $this->ranker->rank(
            $this->deduper->dedupe($suggestions),
            $context,
        );
    }
}
