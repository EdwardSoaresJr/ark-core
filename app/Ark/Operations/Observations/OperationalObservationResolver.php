<?php

namespace App\Ark\Operations\Observations;

use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventTone;
use Illuminate\Support\Carbon;

/**
 * Observation layer — authority events in, operational observations out.
 *
 * Not pressure. Not tasks. Not authority. Not event sourcing.
 * Most authority changes produce zero observations.
 */
final class OperationalObservationResolver
{
    /**
     * @param  list<OperationalEventEntry>  $events
     * @param  array{
     *     customer_id?: ?int,
     *     vehicle_id?: ?int,
     *     repair_order_id?: ?int,
     *     conversation_id?: ?int,
     * }  $entityContext
     * @return list<OperationalObservation>
     */
    public function resolve(array $events, array $entityContext = []): array
    {
        if ($events === []) {
            return [];
        }

        $sorted = collect($events)
            ->sortBy(fn (OperationalEventEntry $event): int => $event->occurredAt->timestamp)
            ->values();

        $entities = $this->mergeEntityContext($entityContext, $sorted);
        $observations = [];

        foreach ($this->estimateObservations($sorted, $entities) as $observation) {
            $observations[] = $observation;
        }

        foreach ($this->communicationObservations($sorted, $entities) as $observation) {
            $observations[] = $observation;
        }

        return $observations;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OperationalEventEntry>  $events
     * @param  array<string, ?int>  $entities
     * @return list<OperationalObservation>
     */
    private function estimateObservations($events, array $entities): array
    {
        $observations = [];
        $viewEvents = $events->filter(
            fn (OperationalEventEntry $event): bool => $event->kind === OperationalEventKind::EstimateViewed,
        );

        foreach ($viewEvents as $event) {
            $context = $this->entitiesForEvent($entities, $event);
            $observations[] = $this->make(
                type: OperationalObservationType::EstimateViewed,
                severity: OperationalObservationSeverity::Medium,
                occurredAt: $event->occurredAt,
                entities: $context,
                headline: 'Estimate viewed',
                description: $event->body ?? 'Customer opened the estimate portal.',
                sourceEvents: [OperationalObservationSourceEvent::fromEntry($event)],
                metadata: $this->sourceMetadata($event),
            );
        }

        $viewCount = $viewEvents->count();

        if ($viewCount >= 2) {
            $latest = $viewEvents->sortByDesc(fn (OperationalEventEntry $event): int => $event->occurredAt->timestamp)->first();
            $context = $this->entitiesForEvent($entities, $latest);

            $observations[] = $this->make(
                type: OperationalObservationType::EstimateViewedMultipleTimes,
                severity: $viewCount >= 4 ? OperationalObservationSeverity::High : OperationalObservationSeverity::Medium,
                occurredAt: $latest->occurredAt,
                entities: $context,
                headline: "Estimate viewed {$viewCount} times",
                description: 'Customer has opened the estimate more than once.',
                sourceEvents: $viewEvents
                    ->map(fn (OperationalEventEntry $event): OperationalObservationSourceEvent => OperationalObservationSourceEvent::fromEntry($event))
                    ->values()
                    ->all(),
                metadata: array_merge($this->sourceMetadata($latest), [
                    'view_count' => $viewCount,
                ]),
            );
        }

        foreach ($events->filter(
            fn (OperationalEventEntry $event): bool => $event->kind === OperationalEventKind::EstimateSent,
        ) as $event) {
            $context = $this->entitiesForEvent($entities, $event);
            $observations[] = $this->make(
                type: OperationalObservationType::EstimateSent,
                severity: OperationalObservationSeverity::Low,
                occurredAt: $event->occurredAt,
                entities: $context,
                headline: 'Estimate sent',
                description: $event->body ?? 'Estimate link sent to customer.',
                sourceEvents: [OperationalObservationSourceEvent::fromEntry($event)],
                metadata: $this->sourceMetadata($event),
            );
        }

        return $observations;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OperationalEventEntry>  $events
     * @param  array<string, ?int>  $entities
     * @return list<OperationalObservation>
     */
    private function communicationObservations($events, array $entities): array
    {
        $observations = [];
        $customerFacing = $events->filter(fn (OperationalEventEntry $event): bool => $this->isCustomerFacing($event));

        if ($customerFacing->isEmpty()) {
            return [];
        }

        $last = $customerFacing->last();

        if ($last !== null && $last->tone === OperationalEventTone::Shop) {
            // Carbon 3 returns float diffs — cast before any operator-facing copy.
            $hoursWaiting = max(0, (int) $last->occurredAt->diffInHours(now()));
            $severity = match (true) {
                $hoursWaiting >= 24 => OperationalObservationSeverity::High,
                $hoursWaiting >= 4 => OperationalObservationSeverity::Medium,
                default => OperationalObservationSeverity::Low,
            };
            $context = $this->entitiesForEvent($entities, $last);
            $hourLabel = $hoursWaiting === 1 ? '1 hour' : "{$hoursWaiting} hours";

            $observations[] = $this->make(
                type: OperationalObservationType::CustomerWaitingResponse,
                severity: $severity,
                occurredAt: $last->occurredAt,
                entities: $context,
                headline: 'Customer waiting response',
                description: $hoursWaiting >= 1
                    ? "Shop replied {$hourLabel} ago — no customer response since."
                    : 'Shop replied recently — waiting on customer response.',
                sourceEvents: [OperationalObservationSourceEvent::fromEntry($last)],
                metadata: array_merge($this->sourceMetadata($last), [
                    'hours_waiting' => $hoursWaiting,
                ]),
            );
        }

        $trailingInbound = $this->trailingCustomerMessages($customerFacing);

        if ($trailingInbound->count() >= 2) {
            $latestInbound = $trailingInbound->last();

            if ($latestInbound !== null) {
                $context = $this->entitiesForEvent($entities, $latestInbound);
                $count = $trailingInbound->count();
                $observations[] = $this->make(
                    type: OperationalObservationType::CustomerSentMultipleMessages,
                    severity: $count >= 3
                        ? OperationalObservationSeverity::High
                        : OperationalObservationSeverity::Medium,
                    occurredAt: $latestInbound->occurredAt,
                    entities: $context,
                    headline: 'Customer sent multiple messages',
                    description: "{$count} customer messages since the last shop reply.",
                    sourceEvents: $trailingInbound
                        ->map(fn (OperationalEventEntry $event): OperationalObservationSourceEvent => OperationalObservationSourceEvent::fromEntry($event))
                        ->values()
                        ->all(),
                    metadata: array_merge($this->sourceMetadata($latestInbound), [
                        'message_count' => $count,
                    ]),
                );
            }
        }

        return $observations;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OperationalEventEntry>  $customerFacing
     * @return \Illuminate\Support\Collection<int, OperationalEventEntry>
     */
    private function trailingCustomerMessages($customerFacing)
    {
        $reversed = $customerFacing->reverse()->values();
        $messages = collect();

        foreach ($reversed as $event) {
            if ($event->tone === OperationalEventTone::Customer) {
                $messages->prepend($event);

                continue;
            }

            if ($event->tone === OperationalEventTone::Shop) {
                break;
            }
        }

        return $messages->values();
    }

    private function isCustomerFacing(OperationalEventEntry $event): bool
    {
        if ($event->kind === OperationalEventKind::InternalNote) {
            return false;
        }

        if ($event->tone === OperationalEventTone::Internal) {
            return false;
        }

        return in_array($event->kind, [
            OperationalEventKind::Sms,
            OperationalEventKind::Email,
            OperationalEventKind::Messenger,
            OperationalEventKind::Call,
            OperationalEventKind::Portal,
            OperationalEventKind::Logged,
            OperationalEventKind::EstimateViewed,
            OperationalEventKind::EstimateSent,
        ], true);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OperationalEventEntry>  $events
     * @param  array{
     *     customer_id?: ?int,
     *     vehicle_id?: ?int,
     *     repair_order_id?: ?int,
     *     conversation_id?: ?int,
     * }  $entityContext
     * @return array<string, ?int>
     */
    private function mergeEntityContext(array $entityContext, $events): array
    {
        $merged = [
            'customer_id' => $entityContext['customer_id'] ?? null,
            'vehicle_id' => $entityContext['vehicle_id'] ?? null,
            'repair_order_id' => $entityContext['repair_order_id'] ?? null,
            'conversation_id' => $entityContext['conversation_id'] ?? null,
        ];

        foreach ($events as $event) {
            foreach (['customer_id', 'vehicle_id', 'repair_order_id', 'conversation_id'] as $key) {
                if ($merged[$key] === null && isset($event->metadata[$key])) {
                    $merged[$key] = (int) $event->metadata[$key];
                }
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, ?int>  $entities
     * @return array<string, ?int>
     */
    private function entitiesForEvent(array $entities, OperationalEventEntry $event): array
    {
        return [
            'customer_id' => isset($event->metadata['customer_id'])
                ? (int) $event->metadata['customer_id']
                : $entities['customer_id'],
            'vehicle_id' => isset($event->metadata['vehicle_id'])
                ? (int) $event->metadata['vehicle_id']
                : $entities['vehicle_id'],
            'repair_order_id' => isset($event->metadata['repair_order_id'])
                ? (int) $event->metadata['repair_order_id']
                : $entities['repair_order_id'],
            'conversation_id' => isset($event->metadata['conversation_id'])
                ? (int) $event->metadata['conversation_id']
                : $entities['conversation_id'],
        ];
    }

    /**
     * @param  array<string, ?int>  $entities
     * @param  list<OperationalObservationSourceEvent>  $sourceEvents
     * @param  array<string, mixed>  $metadata
     */
    private function make(
        OperationalObservationType $type,
        OperationalObservationSeverity $severity,
        Carbon $occurredAt,
        array $entities,
        string $headline,
        string $description,
        array $sourceEvents = [],
        array $metadata = [],
    ): OperationalObservation {
        return new OperationalObservation(
            type: $type,
            severity: $severity,
            occurredAt: $occurredAt,
            customerId: $entities['customer_id'],
            vehicleId: $entities['vehicle_id'],
            repairOrderId: $entities['repair_order_id'],
            conversationId: $entities['conversation_id'],
            headline: $headline,
            description: $description,
            sourceEvents: $sourceEvents,
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceMetadata(OperationalEventEntry $event): array
    {
        return [
            'source' => $event->source->value,
            'event_kind' => $event->kind->value,
            'source_occurred_at' => $event->occurredAt->toIso8601String(),
        ];
    }
}
