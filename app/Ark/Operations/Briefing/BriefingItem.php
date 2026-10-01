<?php

namespace App\Ark\Operations\Briefing;

final readonly class BriefingItem
{
    /**
     * @param  list<BriefingEvidenceItem>  $evidenceItems
     */
    public function __construct(
        public string $key,
        public string $headline,
        public string $summary,
        public BriefingPriority $priority,
        public BriefingConfidence $confidence,
        public array $evidenceItems,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public ?int $repairOrderId = null,
        public ?int $customerId = null,
    ) {}

    public function sortWeight(): int
    {
        return $this->priority->weight();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'headline' => $this->headline,
            'summary' => $this->summary,
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'confidence' => $this->confidence->toArray(),
            'evidence_items' => array_map(
                static fn (BriefingEvidenceItem $item): array => $item->toArray(),
                $this->evidenceItems,
            ),
            'expandable' => $this->evidenceItems !== [],
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'repair_order_id' => $this->repairOrderId,
            'customer_id' => $this->customerId,
        ];
    }
}
