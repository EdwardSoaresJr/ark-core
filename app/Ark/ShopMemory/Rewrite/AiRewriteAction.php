<?php

namespace App\Ark\ShopMemory\Rewrite;

use App\Ark\Operations\Settings\ShopSettings;
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

        $apiKey = trim((string) ShopSettings::current()->openai_api_key);

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI is not configured.');
        }

        // Bounded stub until observation earns real prompt work:
        // compress whitespace and title-case first letter — replace with OpenAI call when enabled on floor.
        $collapsed = preg_replace('/\s+/u', ' ', $input) ?? $input;

        return mb_strtoupper(mb_substr($collapsed, 0, 1)).mb_substr($collapsed, 1);
    }
}
