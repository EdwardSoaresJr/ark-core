<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Sort + limit only. Dedupe and normalize live elsewhere in the pipeline.
 * Ranking algorithm is replaceable — swap this class, keep pipeline shape.
 */
final class SuggestionRanker
{
    /**
     * @param  list<Suggestion>  $suggestions
     * @return list<Suggestion>
     */
    public function rank(array $suggestions, SuggestionContext $context): array
    {
        if ($suggestions === []) {
            return [];
        }

        $ranked = $suggestions;

        usort($ranked, function (Suggestion $a, Suggestion $b) use ($context): int {
            $relevance = $b->relevance <=> $a->relevance;
            if ($relevance !== 0) {
                return $relevance;
            }

            $queryBoost = $this->prefixBoost($b, $context) <=> $this->prefixBoost($a, $context);
            if ($queryBoost !== 0) {
                return $queryBoost;
            }

            return strcmp($a->text, $b->text);
        });

        return array_slice($ranked, 0, max(1, $context->limit));
    }

    private function prefixBoost(Suggestion $suggestion, SuggestionContext $context): int
    {
        $query = $context->normalizedQuery();

        if ($query === '') {
            return 0;
        }

        $text = mb_strtolower($suggestion->text);

        if (str_starts_with($text, $query)) {
            return 2;
        }

        if (str_contains($text, $query)) {
            return 1;
        }

        return 0;
    }
}
