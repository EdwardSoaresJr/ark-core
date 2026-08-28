<?php

namespace App\Ark\Growth\Health;

final readonly class ExplainableHealthScore
{
    /**
     * @param  list<array{label: string, impact: string, detail: string}>  $factors
     */
    public function __construct(
        public string $domain,
        public int $score,
        public string $summary,
        public array $factors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'score' => $this->score,
            'summary' => $this->summary,
            'factors' => $this->factors,
        ];
    }
}
