<?php

namespace App\Ark\Dragon\Agent;

final class DragonToolRegistry
{
    /** @var array<string, DragonAgentTool> */
    private array $tools = [];

    /**
     * @param  iterable<DragonAgentTool>  $tools
     */
    public function __construct(iterable $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }

        DragonOpenAiFunctionNames::index(array_keys($this->tools));
    }

    /**
     * @return list<DragonAgentTool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function get(string $name): DragonAgentTool
    {
        if (isset($this->tools[$name])) {
            return $this->tools[$name];
        }

        $canonical = DragonOpenAiFunctionNames::toCanonical($name, array_keys($this->tools));

        return $this->tools[$canonical];
    }

    /**
     * OpenAI tools payload.
     *
     * @return list<array<string, mixed>>
     */
    public function openaiTools(): array
    {
        return array_map(fn (DragonAgentTool $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => DragonOpenAiFunctionNames::toProvider($tool->name()),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
        ], $this->all());
    }
}
