<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Models\User;
use Illuminate\Support\Carbon;

final class RecordRecommendationEventAction
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function handle(
        Recommendation $recommendation,
        RecommendationEventType $type,
        ?User $actor = null,
        ?RepairOrder $repairOrder = null,
        ?RepairOrderConcern $concern = null,
        ?int $amountCents = null,
        ?RecommendationDecisionReason $reason = null,
        ?string $note = null,
        ?array $payload = null,
        Carbon|string|null $occurredAt = null,
    ): RecommendationEvent {
        return $recommendation->events()->create([
            'type' => $type,
            'occurred_at' => $occurredAt ?? now(),
            'actor_user_id' => $actor?->id,
            'repair_order_id' => $repairOrder?->id,
            'repair_order_concern_id' => $concern?->id,
            'amount_cents' => $amountCents,
            'reason_code' => $reason?->value,
            'note' => filled($note) ? trim((string) $note) : null,
            'payload' => $payload,
        ]);
    }
}
