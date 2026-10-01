<?php

namespace App\Ark\Operations\Recommendations;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class DismissRecommendationAction
{
    public function __construct(
        private readonly RecordRecommendationEventAction $events,
    ) {}

    public function handle(
        Recommendation $recommendation,
        ?User $actor = null,
        ?string $reason = null,
        ?string $note = null,
    ): Recommendation {
        if ($recommendation->lifecycle === RecommendationLifecycle::Resolved) {
            throw ValidationException::withMessages([
                'recommendation' => 'Resolved recommendations cannot be dismissed.',
            ]);
        }

        $recommendation->forceFill([
            'lifecycle' => RecommendationLifecycle::Dismissed,
            'dismissed_at' => now(),
            'dismissal_reason' => filled($reason) ? trim($reason) : null,
            'dismissal_note' => filled($note) ? trim($note) : null,
            'follow_up_completed_at' => $recommendation->follow_up_completed_at ?? now(),
        ])->save();

        $this->events->handle(
            $recommendation,
            RecommendationEventType::Dismissed,
            actor: $actor,
            note: $note,
            payload: [
                'dismissal_reason' => $recommendation->dismissal_reason,
            ],
        );

        return $recommendation->fresh() ?? $recommendation;
    }
}
