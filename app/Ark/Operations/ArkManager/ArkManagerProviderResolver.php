<?php

namespace App\Ark\Operations\ArkManager;

final class ArkManagerProviderResolver
{
    public function __construct(
        private readonly DeterministicAiManagerProvider $deterministicProvider,
    ) {}

    public function resolve(): AiManagerProvider
    {
        return $this->deterministicProvider;
    }
}
