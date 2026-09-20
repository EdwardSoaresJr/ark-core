<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Stable suggestion identity. Not a database id — reuse/tracking key.
 */
final class SuggestionIdentity
{
    public static function make(string $providerKey, string $displayText): string
    {
        $normalized = mb_strtolower(trim($displayText));

        return $providerKey.':'.hash('xxh3', $normalized);
    }
}
