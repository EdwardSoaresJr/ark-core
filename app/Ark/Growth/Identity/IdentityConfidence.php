<?php

namespace App\Ark\Growth\Identity;

final readonly class IdentityConfidence
{
    /**
     * @param  list<array{label: string, satisfied: bool}>  $signals
     * @param  list<array{label: string, value: string}>  $facts
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public int $score,
        public string $reason,
        public array $signals = [],
        public array $facts = [],
        public array $evidence = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'reason' => $this->reason,
            'signals' => $this->signals,
            'facts' => $this->facts,
            'evidence' => $this->evidence,
        ];
    }

    public static function anonymous(): self
    {
        return new self(
            score: 25,
            reason: 'Anonymous visitor — session not yet linked to a customer or lead.',
            signals: [
                ['label' => 'No customer match', 'satisfied' => false],
                ['label' => 'No lead match', 'satisfied' => false],
            ],
            facts: [],
        );
    }
}
