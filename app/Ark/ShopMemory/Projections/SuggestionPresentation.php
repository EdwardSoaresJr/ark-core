<?php

namespace App\Ark\ShopMemory\Projections;

use App\Ark\ShopMemory\Suggestion\Suggestion;
use App\Ark\ShopMemory\Suggestion\SuggestionCorpus;
use App\Ark\ShopMemory\Suggestion\SuggestionResult;

/**
 * Shared presentation rules for Shop Memory projections.
 * Providers return facts; projections own formatting/truncation.
 */
final class SuggestionPresentation
{
    public const MAX_DISPLAY_LENGTH = 160;

    public function present(Suggestion $suggestion, string $query): PresentedSuggestion
    {
        return new PresentedSuggestion(
            id: $suggestion->id,
            display: $this->formatDisplay($suggestion->text),
            providerKey: $suggestion->providerKey,
            score: $suggestion->relevance,
            meta: $suggestion->meta,
            query: $query,
        );
    }

    /**
     * @return list<PresentedSuggestion>
     */
    public function presentMany(SuggestionResult $result): array
    {
        return array_map(
            fn (Suggestion $suggestion): PresentedSuggestion => $this->present($suggestion, $result->query),
            $result->items,
        );
    }

    public function formatDisplay(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        if (mb_strlen($collapsed) <= self::MAX_DISPLAY_LENGTH) {
            return $collapsed;
        }

        return rtrim(mb_substr($collapsed, 0, self::MAX_DISPLAY_LENGTH - 1)).'…';
    }
}
