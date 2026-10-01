<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Text normalization for dedupe keys. Not presentation.
 */
final class SuggestionTextNormalizer
{
    public function normalize(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim(mb_strtolower($text))) ?? '';

        return $collapsed;
    }
}
