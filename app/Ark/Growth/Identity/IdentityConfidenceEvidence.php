<?php

namespace App\Ark\Growth\Identity;

/**
 * Structured explainability for identity confidence — signals (why) + facts (evidence).
 */
final readonly class IdentityConfidenceEvidence
{
    /**
     * @param  list<array{label: string, satisfied: bool}>  $signals
     * @param  list<array{label: string, value: string}>  $facts
     */
    public function __construct(
        public int $score,
        public string $reason,
        public array $signals = [],
        public array $facts = [],
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
        ];
    }

    public static function fromConfidence(IdentityConfidence $confidence): self
    {
        return new self(
            score: $confidence->score,
            reason: $confidence->reason,
            signals: $confidence->signals,
            facts: $confidence->facts,
        );
    }
}
