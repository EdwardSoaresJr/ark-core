<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ResolveRecommendationAction
{
    public function __construct(
        private readonly RecordRecommendationEventAction $events,
    ) {}

    public function handle(
        Recommendation $recommendation,
        RecommendationResolvedReason $reason,
        ?User $actor = null,
        ?RepairOrder $repairOrder = null,
        ?int $resolvedMileage = null,
        ?string $note = null,
    ): Recommendation {
        if ($recommendation->lifecycle === RecommendationLifecycle::Dismissed) {
            throw ValidationException::withMessages([
                'recommendation' => 'Dismissed recommendations cannot be resolved.',
            ]);
        }

        $recommendation->forceFill([
            'lifecycle' => RecommendationLifecycle::Resolved,
            'resolved_at' => now(),
            'resolved_mileage' => $resolvedMileage ?? $repairOrder?->resolvedMileageIn(),
            'resolved_reason' => $reason,
            'resolved_note' => filled($note) ? trim($note) : null,
            'follow_up_completed_at' => $recommendation->follow_up_completed_at ?? now(),
        ])->save();

        $this->events->handle(
            $recommendation,
            RecommendationEventType::Resolved,
            actor: $actor,
            repairOrder: $repairOrder,
            note: $note,
            payload: [
                'resolved_reason' => $reason->value,
                'resolved_mileage' => $recommendation->resolved_mileage,
            ],
        );

        return $recommendation->fresh() ?? $recommendation;
    }
}
