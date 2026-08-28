<?php

namespace App\Ark\Dragon\Agent;

interface DragonAgentTool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema object for OpenAI function parameters.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function invoke(array $arguments): array;
}
