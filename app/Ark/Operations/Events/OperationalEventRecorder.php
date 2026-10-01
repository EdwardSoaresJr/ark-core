<?php

namespace App\Ark\Operations\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class OperationalEventRecorder
{
    private const COMMAND_PREFIXES = [
        'add_',
        'approve_',
        'create_',
        'delete_',
        'generate_',
        'show_',
        'update_',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        OperationalEventName|string $eventName,
        Model|string $aggregate,
        ?int $aggregateId = null,
        ?User $actor = null,
        array $payload = [],
    ): OperationalEvent {
        $name = $eventName instanceof OperationalEventName ? $eventName->value : $eventName;

        $this->ensurePastTenseFact($name);

        [$aggregateType, $resolvedAggregateId] = $this->resolveAggregate($aggregate, $aggregateId);

        return OperationalEvent::query()->create([
            'event_name' => $name,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $resolvedAggregateId,
            'actor_user_id' => $actor?->id,
            'occurred_at' => now(),
            'payload_json' => $payload === [] ? null : $payload,
        ]);
    }

    private function ensurePastTenseFact(string $eventName): void
    {
        foreach (self::COMMAND_PREFIXES as $prefix) {
            if (str_starts_with($eventName, $prefix)) {
                throw new InvalidArgumentException('Operational event names must describe historical facts, not commands.');
            }
        }

        if (preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $eventName) !== 1) {
            throw new InvalidArgumentException('Operational event names must be snake_case.');
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveAggregate(Model|string $aggregate, ?int $aggregateId): array
    {
        if ($aggregate instanceof Model) {
            return [$aggregate::class, (int) $aggregate->getKey()];
        }

        if ($aggregateId === null) {
            throw new InvalidArgumentException('Aggregate id is required when aggregate type is provided as a string.');
        }

        return [$aggregate, $aggregateId];
    }
}
