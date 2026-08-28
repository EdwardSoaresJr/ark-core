<?php

namespace App\Ark\Growth\Journey;

use Illuminate\Support\Carbon;

/**
 * Raw timeline fact before story composition.
 */
final readonly class JourneyTimelineEntry
{
    /**
     * @param  array<string, mixed>  $evidence
     * @param  list<JourneyEvidenceItem>  $evidenceItems
     */
    public function __construct(
        public string $key,
        public JourneyMilestoneCategory $category,
        public string $headline,
        public string $detail,
        public Carbon $occurredAt,
        public int $sortWeight = 0,
        public array $evidence = [],
        public ?string $aggregateKey = null,
        public array $evidenceItems = [],
    ) {}
}
