<?php

namespace App\Ark\Growth\Journey;

use Illuminate\Support\Carbon;

/**
 * One immutable source row behind a journey milestone summary.
 */
final readonly class JourneyEvidenceItem
{
    public function __construct(
        public JourneyEvidenceSource $source,
        public string $summary,
        public Carbon $occurredAt,
        public ?string $detail = null,
        public ?int $sourceId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'summary' => $this->summary,
            'detail' => $this->detail,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'occurred_label' => $this->occurredAt->format('M j g:i A'),
            'source_id' => $this->sourceId,
        ];
    }
}
