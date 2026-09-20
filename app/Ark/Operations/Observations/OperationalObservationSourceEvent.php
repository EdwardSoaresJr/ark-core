<?php

namespace App\Ark\Operations\Observations;

use App\Ark\Operations\Timeline\OperationalEventEntry;
use Illuminate\Support\Carbon;

/**
 * Snapshot of a timeline event that produced an observation.
 */
final readonly class OperationalObservationSourceEvent
{
    public function __construct(
        public string $source,
        public string $kind,
        public Carbon $occurredAt,
        public string $headline,
        public ?string $body,
    ) {}

    public static function fromEntry(OperationalEventEntry $event): self
    {
        return new self(
            source: $event->source->value,
            kind: $event->kind->value,
            occurredAt: $event->occurredAt,
            headline: $event->headline,
            body: $event->body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'kind' => $this->kind,
            'occurred_at' => $this->occurredAt,
            'headline' => $this->headline,
            'body' => $this->body,
            'occurred_at_label' => $this->occurredAt
                ->timezone(config('app.display_timezone'))
                ->format('M j, g:i A'),
        ];
    }
}
