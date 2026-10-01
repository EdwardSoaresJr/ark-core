<?php

namespace App\Ark\Operations\ArkManager;

final readonly class ArkManagerContext
{
    /**
     * @param  list<array<string, mixed>>  $pipeline
     * @param  list<array<string, mixed>>  $flowStages
     * @param  list<array<string, mixed>>  $recommendations
     * @param  list<array<string, mixed>>  $commitments
     */
    public function __construct(
        public string $shopDate,
        public array $pipeline,
        public ?array $flowConstraint,
        public array $flowStages,
        public array $recommendations,
        public array $commitments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'shop_date' => $this->shopDate,
            'pipeline' => $this->pipeline,
            'flow_constraint' => $this->flowConstraint,
            'flow_stages' => $this->flowStages,
            'recommendations' => $this->recommendations,
            'commitments' => $this->commitments,
        ];
    }
}
