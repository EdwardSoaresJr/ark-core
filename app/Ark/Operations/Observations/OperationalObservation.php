<?php

namespace App\Ark\Operations\Observations;

use Illuminate\Support\Carbon;

/**
 * Observation read model — interpretive truth derived from authority events, not stored authority.
 *
 * Authority events are factual (timeline / OperationalEventKind). Observations reason about them.
 * Orientation consumes observations; surfaces must not re-derive business meaning from authorities.
 */
final readonly class OperationalObservation
{
    /**
     * @param  list<OperationalObservationSourceEvent>  $sourceEvents
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public OperationalObservationType $type,
        public OperationalObservationSeverity $severity,
        public Carbon $occurredAt,
        public ?int $customerId,
        public ?int $vehicleId,
        public ?int $repairOrderId,
        public ?int $conversationId,
        public string $headline,
        public string $description,
        public array $sourceEvents = [],
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'occurred_at' => $this->occurredAt,
            'customer_id' => $this->customerId,
            'vehicle_id' => $this->vehicleId,
            'repair_order_id' => $this->repairOrderId,
            'conversation_id' => $this->conversationId,
            'headline' => $this->headline,
            'description' => $this->description,
            'source_events' => array_map(
                fn (OperationalObservationSourceEvent $event): array => $event->toArray(),
                $this->sourceEvents,
            ),
            'metadata' => $this->metadata,
        ];
    }
}
