<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * One suggestion from one provider. Not authority.
 *
 * Identity is stable for ranking/dedupe within a request; it is not a DB id.
 */
final class Suggestion
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly string $providerKey,
        public readonly SuggestionCorpus $corpus,
        public readonly float $relevance = 0.0,
        public readonly array $meta = [],
    ) {}
}
