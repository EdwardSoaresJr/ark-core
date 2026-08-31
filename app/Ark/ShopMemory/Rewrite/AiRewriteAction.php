<?php

namespace App\Ark\ShopMemory\Rewrite;

use App\Ark\ShopMemory\ShopMemoryFeatures;
use RuntimeException;

/**
 * Explicit Rewrite only — never on blur, never silent authorship.
 * Disabled until ShopMemoryFeatures::aiRewriteEnabled().
 */
final class AiRewriteAction
{
    public function rewrite(string $text): string
    {
        if (! ShopMemoryFeatures::aiRewriteEnabled()) {
            throw new RuntimeException('AI Rewrite is disabled for this shop.');
        }

        $input = trim($text);

        if ($input === '') {
            throw new RuntimeException('Nothing to rewrite.');
        }

        throw new RuntimeException('AI rewrite is not configured.');
    }
}
