<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Query context for Shop Memory suggestions.
 * Providers receive this; they do not invent product-specific coupling.
 */
final class SuggestionContext
{
    /**
     * @param  list<string>  $providerKeys  Empty = all providers that serve the corpus
     */
    public function __construct(
        public readonly string $query,
        public readonly SuggestionCorpus $corpus,
        public readonly int $limit = 8,
        public readonly ?int $repairOrderId = null,
        public readonly array $providerKeys = [],
    ) {}

    public function normalizedQuery(): string
    {
        return mb_strtolower(trim($this->query));
    }
}
