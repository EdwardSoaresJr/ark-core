<?php

namespace App\Ark\Dragon\Agent;

final class DragonModelTurn
{
    /**
     * @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls,
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly ?string $finishReason = null,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
