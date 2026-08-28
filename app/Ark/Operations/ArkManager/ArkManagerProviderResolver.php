<?php

namespace App\Ark\Operations\ArkManager;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;

final class ArkManagerProviderResolver
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
        private readonly OpenAiManagerProvider $openAiProvider,
        private readonly DeterministicAiManagerProvider $deterministicProvider,
    ) {}

    public function resolve(): AiManagerProvider
    {
        if ($this->credentials->openaiConfigured()) {
            return $this->openAiProvider;
        }

        return $this->deterministicProvider;
    }
}
