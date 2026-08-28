<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * One Shop Memory provider. Never calls other providers. Never ranks globally.
 *
 * Providers observe only their own corpus. They never inspect or coordinate
 * with other providers. Returns suggestions for corpora it serves.
 * Engine owns composition.
 */
interface SuggestionProvider
{
    /** Stable machine key (registration + suggestion identity). */
    public function key(): string;

    /** Human label for diagnostics. */
    public function name(): string;

    /** One-line purpose for diagnostics. */
    public function description(): string;

    /** Provider contract version (bump when suggest shape/meta changes). */
    public function version(): string;

    /**
     * Corpora this provider can answer.
     *
     * @return list<SuggestionCorpus>
     */
    public function corpora(): array;

    /**
     * @return list<Suggestion>
     */
    public function suggest(SuggestionContext $context): array;
}
