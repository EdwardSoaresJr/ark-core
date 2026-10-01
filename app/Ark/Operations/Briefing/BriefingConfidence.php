<?php

namespace App\Ark\Operations\Briefing;

final readonly class BriefingConfidence
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
}
