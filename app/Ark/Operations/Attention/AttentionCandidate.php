<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Observations\OperationalObservation;

/**
 * Explainable attention projection — reasons first, score second.
 */
final readonly class AttentionCandidate
{
    /**
     * @param  list<string>  $reasons
     * @param  list<OperationalObservation>  $observations
     */
    public function __construct(
        public string $entityKey,
        public string $headline,
        public int $pressureScore,
        public array $reasons,
        public array $observations,
        public ?int $conversationId = null,
        public ?int $customerId = null,
        public ?int $repairOrderId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_key' => $this->entityKey,
            'headline' => $this->headline,
            'pressure_score' => $this->pressureScore,
            'reasons' => $this->reasons,
            'conversation_id' => $this->conversationId,
            'customer_id' => $this->customerId,
            'repair_order_id' => $this->repairOrderId,
            'observations' => array_map(
                fn (OperationalObservation $observation): array => $observation->toArray(),
                $this->observations,
            ),
        ];
    }
}
