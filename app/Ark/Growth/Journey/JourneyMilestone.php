<?php

namespace App\Ark\Growth\Journey;

use Illuminate\Support\Carbon;

final readonly class JourneyMilestone
{
    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $metrics
     * @param  list<JourneyEvidenceItem>  $evidenceItems
     */
    public function __construct(
        public string $key,
        public JourneyMilestoneCategory $category,
        public string $headline,
        public string $detail,
        public Carbon $occurredAt,
        public array $evidence = [],
        public array $metrics = [],
        public array $evidenceItems = [],
    ) {}

    public function expandable(): bool
    {
        return $this->evidenceItems !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'headline' => $this->headline,
            'detail' => $this->detail,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'occurred_label' => $this->occurredAt->format('M j • g:i A'),
            'evidence' => $this->evidence,
            'metrics' => $this->metrics,
            'expandable' => $this->expandable(),
            'evidence_items' => array_map(
                static fn (JourneyEvidenceItem $item): array => $item->toArray(),
                $this->evidenceItems,
            ),
        ];
    }
}
