<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Ranked suggestion list from the engine. Disposable projection payload.
 */
final class SuggestionResult
{
    /**
     * @param  list<Suggestion>  $items
     * @param  list<ProviderExecutionTrace>  $traces  Debug/diagnostics only
     */
    public function __construct(
        public readonly array $items,
        public readonly string $query,
        public readonly SuggestionCorpus $corpus,
        public readonly array $traces = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return list<string>
     */
    public function texts(): array
    {
        return array_map(static fn (Suggestion $s): string => $s->text, $this->items);
    }
}
