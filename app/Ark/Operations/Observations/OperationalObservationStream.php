<?php

namespace App\Ark\Operations\Observations;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Curated operational observation stream — not event sourcing, not every authority write.
 *
 * This stream exists so that every operator surface explains
 * the same operational change in the same way.
 *
 * Surfaces consume observations.
 *
 * They do not reinterpret authorities.
 *
 * Pipeline: Authority → Authority Event → Observation → Stream → Surface.
 * Resolution on one operator action must clear the stream entry for every consumer.
 */
final class OperationalObservationStream
{
    public function emit(OperationalObservation $observation, string $dedupeKey, ?string $sourceEventName = null): OperationalObservationStreamEntry
    {
        $existing = OperationalObservationStreamEntry::query()
            ->where('dedupe_key', $dedupeKey)
            ->first();

        $attributes = [
            'observation_type' => $observation->type->value,
            'customer_id' => $observation->customerId,
            'conversation_id' => $observation->conversationId,
            'repair_order_id' => $observation->repairOrderId,
            'headline' => $observation->headline,
            'description' => $observation->description,
            'source_event_name' => $sourceEventName,
            'source_aggregate_type' => $observation->metadata['aggregate_type'] ?? null,
            'source_aggregate_id' => $observation->metadata['aggregate_id'] ?? null,
            'occurred_at' => $observation->occurredAt,
            'resolved_at' => null,
            'resolved_by_user_id' => null,
            'metadata' => $observation->metadata === [] ? null : $observation->metadata,
        ];

        if ($existing === null) {
            return OperationalObservationStreamEntry::query()->create([
                ...$attributes,
                'dedupe_key' => $dedupeKey,
            ]);
        }

        $existing->fill($attributes)->save();

        return $existing->fresh();
    }

    public function resolve(string $dedupeKey, ?User $actor = null): void
    {
        OperationalObservationStreamEntry::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now(),
                'resolved_by_user_id' => $actor?->id,
            ]);
    }

    public function resolveCustomerRepliedForConversation(int $conversationId, ?User $actor = null): void
    {
        $this->resolve($this->customerRepliedDedupeKey($conversationId), $actor);
    }

    public function customerRepliedDedupeKey(int $conversationId): string
    {
        return 'customer_replied:conversation:'.$conversationId;
    }

    /**
     * @return Collection<int, OperationalObservationStreamEntry>
     */
    public function active(?Carbon $since = null, int $limit = 25): Collection
    {
        return OperationalObservationStreamEntry::query()
            ->whereNull('resolved_at')
            ->when($since !== null, fn ($query) => $query->where('occurred_at', '>', $since))
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    public function activeCount(?Carbon $since = null): int
    {
        return OperationalObservationStreamEntry::query()
            ->whereNull('resolved_at')
            ->when($since !== null, fn ($query) => $query->where('occurred_at', '>', $since))
            ->count();
    }
}
