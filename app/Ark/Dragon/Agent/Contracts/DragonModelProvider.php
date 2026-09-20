<?php

namespace App\Ark\Dragon\Agent\Contracts;

use App\Ark\Dragon\Agent\DragonModelTurn;

interface DragonModelProvider
{
    /**
     * @param  list<array{role: string, content: ?string, tool_calls?: mixed, tool_call_id?: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     */
    public function complete(array $messages, array $tools = []): DragonModelTurn;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function structured(array $messages, array $schema): array;

    public function providerName(): string;

    public function modelName(): string;

    /**
     * @return array{ok: bool, provider: string, model: string, detail: string}
     */
    public function health(): array;
}
