<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;

final class CommunicationInteractionAnalysisSummarizer
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarize(string $interactionKind, array $context, string $transcript): array
    {
        if (! $this->credentials->openaiConfigured()) {
            throw new \RuntimeException('Model provider is not configured.');
        }

        throw new \RuntimeException('Model provider is not configured.');
    }
}
