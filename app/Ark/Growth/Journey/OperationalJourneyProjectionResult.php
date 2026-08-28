<?php

namespace App\Ark\Growth\Journey;

use App\Ark\Growth\Identity\IdentityConfidence;
use App\Ark\Growth\Models\GrowthSession;

final readonly class OperationalJourneyProjectionResult
{
    /**
     * @param  list<JourneyMilestone>  $milestones
     * @param  list<array{key: string, label: string, value: string, meta: string|null}>  $summaryCards
     * @param  list<string>  $pathLabels
     */
    public function __construct(
        public ?GrowthSession $session,
        public IdentityConfidence $identityConfidence,
        public array $milestones,
        public array $summaryCards,
        public array $pathLabels,
        public ?int $durationDays,
        public bool $hasStory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->session?->id,
            'identity_confidence' => $this->identityConfidence->toArray(),
            'milestones' => array_map(static fn (JourneyMilestone $m): array => $m->toArray(), $this->milestones),
            'summary_cards' => $this->summaryCards,
            'path_labels' => $this->pathLabels,
            'duration_days' => $this->durationDays,
            'has_story' => $this->hasStory,
        ];
    }
}
