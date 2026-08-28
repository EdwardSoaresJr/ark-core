<?php

namespace App\Ark\Operations\Timeline;

use Illuminate\Support\Carbon;

/**
 * Unified read-model event for operational timelines.
 *
 * Not authority — composes existing stores into one renderable shape.
 */
final readonly class OperationalEventEntry
{
    /**
     * @param  array<string, string>  $links
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public OperationalEventSource $source,
        public OperationalEventKind $kind,
        public Carbon $occurredAt,
        public string $headline,
        public ?string $body,
        public ?string $actor,
        public OperationalEventTone $tone,
        public array $links = [],
        public array $metadata = [],
        public mixed $subject = null,
    ) {}

    /**
     * @return array{
     *     source: string,
     *     kind: string,
     *     occurred_at: Carbon,
     *     headline: string,
     *     body: ?string,
     *     actor: ?string,
     *     tone: string,
     *     links: array<string, string>,
     *     metadata: array<string, mixed>,
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source->value,
            'kind' => $this->kind->value,
            'occurred_at' => $this->occurredAt,
            'headline' => $this->headline,
            'body' => $this->body,
            'actor' => $this->actor,
            'tone' => $this->tone->value,
            'links' => $this->links,
            'metadata' => $this->metadata,
        ];
    }

    public function hubFilter(): string
    {
        $filter = $this->metadata['hub_filter'] ?? null;

        return is_string($filter) && $filter !== '' ? $filter : 'logged';
    }
}
