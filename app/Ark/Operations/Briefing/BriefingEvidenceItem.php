<?php

namespace App\Ark\Operations\Briefing;

use Illuminate\Support\Carbon;

final readonly class BriefingEvidenceItem
{
    public function __construct(
        public string $sourceType,
        public string $summary,
        public Carbon $occurredAt,
        public ?string $detail = null,
        public ?int $sourceId = null,
        public ?string $sourceLabel = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_label' => $this->sourceLabel ?? $this->sourceType,
            'summary' => $this->summary,
            'detail' => $this->detail,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'occurred_label' => $this->occurredAt->format('M j g:i A'),
            'source_id' => $this->sourceId,
        ];
    }
}
