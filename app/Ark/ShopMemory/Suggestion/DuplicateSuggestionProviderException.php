<?php

namespace App\Ark\ShopMemory\Suggestion;

use RuntimeException;

final class DuplicateSuggestionProviderException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Shop Memory provider already registered: {$key}");
    }
}
