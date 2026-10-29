<?php

namespace App\Ark\Operations\Communications;

/**
 * Stock shops do not summarize calls or messages.
 * Hosted summarization is bound over this class when Dragon is entitled.
 */
class CommunicationInteractionAnalysisSummarizer
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarize(string $interactionKind, array $context, string $transcript): array
    {
        throw new \RuntimeException('Model provider is not configured.');
    }
}
