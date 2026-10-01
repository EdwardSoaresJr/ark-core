<?php

namespace App\Ark\Dragon\Agent\Providers;

use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;
use App\Ark\Dragon\Agent\DragonModelTurn;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;

final class NotConfiguredDragonProvider implements DragonModelProvider
{
    private const MESSAGE = 'Dragon model provider is not configured.';

    public function complete(array $messages, array $tools = []): DragonModelTurn
    {
        throw new DragonProviderUnavailable(self::MESSAGE);
    }

    public function structured(array $messages, array $schema): array
    {
        throw new DragonProviderUnavailable(self::MESSAGE);
    }

    public function providerName(): string
    {
        return 'none';
    }

    public function modelName(): string
    {
        return 'none';
    }

    public function health(): array
    {
        return [
            'ok' => false,
            'provider' => $this->providerName(),
            'model' => $this->modelName(),
            'detail' => self::MESSAGE,
        ];
    }
}
