<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Dedupe by normalized display text. Keeps higher relevance.
 */
final class SuggestionDeduper
{
    public function __construct(
        private readonly SuggestionTextNormalizer $normalizer = new SuggestionTextNormalizer,
    ) {}

    /**
     * @param  list<Suggestion>  $suggestions
     * @return list<Suggestion>
     */
    public function dedupe(array $suggestions): array
    {
        $bestByNormalized = [];

        foreach ($suggestions as $suggestion) {
            $normalized = $this->normalizer->normalize($suggestion->text);

            if ($normalized === '') {
                continue;
            }

            $existing = $bestByNormalized[$normalized] ?? null;

            if ($existing === null || $suggestion->relevance > $existing->relevance) {
                $bestByNormalized[$normalized] = $suggestion;
            }
        }

        return array_values($bestByNormalized);
    }
}
