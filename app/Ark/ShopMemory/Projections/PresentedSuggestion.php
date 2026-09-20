<?php

namespace App\Ark\ShopMemory\Projections;

/**
 * Presentation shape for a suggestion. Provider facts → surface display.
 * Does not invent authority.
 */
final class PresentedSuggestion
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $id,
        public readonly string $display,
        public readonly string $providerKey,
        public readonly float $score,
        public readonly array $meta = [],
        public readonly string $query = '',
    ) {}
}
