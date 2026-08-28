<?php

namespace App\Ark\Growth\Opportunities;

final readonly class OpportunityEstimatedLift
{
    /**
     * @param  list<array{label: string, value: string}>  $steps
     */
    public function __construct(
        public string $currentLabel,
        public string $potentialLabel,
        public string $priorityLabel,
        public array $steps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current_label' => $this->currentLabel,
            'potential_label' => $this->potentialLabel,
            'priority_label' => $this->priorityLabel,
            'steps' => $this->steps,
        ];
    }
}
