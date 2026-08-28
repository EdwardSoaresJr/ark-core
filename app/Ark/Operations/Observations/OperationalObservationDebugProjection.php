<?php

namespace App\Ark\Operations\Observations;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Timeline\Mappers\CommunicationEventMapper;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use Illuminate\Support\Facades\Schema;

/**
 * Admin debug projection — surfaces observations derived from live operational truth.
 */
final class OperationalObservationDebugProjection
{
    public function __construct(
        private readonly UnifiedOperationalTimeline $timeline,
        private readonly CommunicationEventMapper $communicationEventMapper,
        private readonly OperationalObservationResolver $observationResolver,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     counts: array{total: int, by_severity: array<string, int>},
     * }
     */
    public function resolve(int $conversationLimit = 25, int $repairOrderLimit = 25): array
    {
        if (! Schema::hasTable('conversations')) {
            return ['rows' => [], 'counts' => ['total' => 0, 'by_severity' => []]];
        }

        $rows = collect();

        Conversation::query()
            ->openPosture()
            ->orderByDesc('updated_at')
            ->limit($conversationLimit)
            ->get()
            ->each(function (Conversation $conversation) use ($rows): void {
                $events = $this->timeline->forConversation($conversation)->all();
                $context = [
                    'conversation_id' => $conversation->id,
                    'customer_id' => $this->customerIdForConversation($conversation),
                ];

                foreach ($this->observationResolver->resolve($events, $context) as $observation) {
                    $rows->push($this->debugRow(
                        $observation,
                        'Conversation #'.$conversation->id,
                    ));
                }
            });

        if (Schema::hasTable('communication_events')) {
            RepairOrder::query()
                ->whereIn('status', RepairOrderStatus::operationalQueueValues())
                ->with([
                    'communicationEvents' => fn ($query) => $query->orderByDesc('occurred_at')->limit(20),
                    'vehicle',
                    'customer',
                ])
                ->orderByDesc('updated_at')
                ->limit($repairOrderLimit)
                ->get()
                ->each(function (RepairOrder $repairOrder) use ($rows): void {
                    $events = $repairOrder->communicationEvents
                        ->map(fn (CommunicationEvent $event): OperationalEventEntry => $this->communicationEventMapper->map($event))
                        ->sortBy(fn (OperationalEventEntry $event): int => $event->occurredAt->timestamp)
                        ->values()
                        ->all();

                    if ($events === []) {
                        return;
                    }

                    $context = [
                        'repair_order_id' => $repairOrder->id,
                        'customer_id' => $repairOrder->customer_id,
                        'vehicle_id' => $repairOrder->vehicle_id,
                    ];

                    foreach ($this->observationResolver->resolve($events, $context) as $observation) {
                        $rows->push($this->debugRow(
                            $observation,
                            'RO #'.$repairOrder->repair_order_id,
                        ));
                    }
                });
        }

        $sorted = $rows
            ->sortByDesc(fn (array $row): int => $row['occurred_at_ts'])
            ->values();

        return [
            'rows' => $sorted->all(),
            'counts' => [
                'total' => $sorted->count(),
                'by_severity' => $sorted->countBy(fn (array $row): string => (string) ($row['severity'] ?? 'low'))->all(),
            ],
        ];
    }

    private function customerIdForConversation(Conversation $conversation): ?int
    {
        return ConversationLink::query()
            ->where('conversation_id', $conversation->id)
            ->where('linkable_type', Customer::class)
            ->value('linkable_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function debugRow(OperationalObservation $observation, string $entityLabel): array
    {
        return [
            'type' => $observation->type->value,
            'type_label' => $observation->type->label(),
            'category' => $observation->type->category(),
            'severity' => $observation->severity->value,
            'severity_label' => $observation->severity->label(),
            'headline' => $observation->headline,
            'description' => $observation->description,
            'source' => $observation->metadata['source'] ?? 'unknown',
            'entity' => $entityLabel,
            'occurred_at' => $observation->occurredAt,
            'occurred_at_ts' => $observation->occurredAt->timestamp,
            'age_label' => $observation->occurredAt->diffForHumans(short: true),
            'customer_id' => $observation->customerId,
            'vehicle_id' => $observation->vehicleId,
            'repair_order_id' => $observation->repairOrderId,
            'conversation_id' => $observation->conversationId,
            'metadata' => $observation->metadata,
            'source_events' => array_map(
                fn (OperationalObservationSourceEvent $event): array => $event->toArray(),
                $observation->sourceEvents,
            ),
        ];
    }
}
