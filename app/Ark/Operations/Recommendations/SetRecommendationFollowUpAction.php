<?php

namespace App\Ark\Operations\Recommendations;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class SetRecommendationFollowUpAction
{
    public function __construct(
        private readonly RecordRecommendationEventAction $events,
    ) {}

    public function schedule(
        Recommendation $recommendation,
        Carbon|string $followUpAt,
        ?User $actor = null,
        ?int $ownerUserId = null,
        ?string $note = null,
    ): Recommendation {
        if (! $recommendation->isOpen()) {
            throw ValidationException::withMessages([
                'follow_up_at' => 'Follow-up can only be scheduled on an open recommendation.',
            ]);
        }

        $when = $followUpAt instanceof Carbon ? $followUpAt : Carbon::parse($followUpAt);

        $recommendation->forceFill([
            'follow_up_at' => $when,
            'follow_up_owner_user_id' => $ownerUserId ?? $actor?->id ?? $recommendation->follow_up_owner_user_id,
            'follow_up_completed_at' => null,
            'follow_up_snoozed_until' => null,
        ])->save();

        $this->events->handle(
            $recommendation,
            RecommendationEventType::FollowUpSet,
            actor: $actor,
            note: $note,
            payload: [
                'follow_up_at' => $when->toIso8601String(),
                'follow_up_owner_user_id' => $recommendation->follow_up_owner_user_id,
            ],
        );

        return $recommendation->fresh() ?? $recommendation;
    }

    public function complete(Recommendation $recommendation, ?User $actor = null, ?string $note = null): Recommendation
    {
        $recommendation->forceFill([
            'follow_up_completed_at' => now(),
            'follow_up_snoozed_until' => null,
        ])->save();

        $this->events->handle(
            $recommendation,
            RecommendationEventType::FollowUpCompleted,
            actor: $actor,
            note: $note,
        );

        return $recommendation->fresh() ?? $recommendation;
    }

    public function snooze(
        Recommendation $recommendation,
        Carbon|string $until,
        ?User $actor = null,
        ?string $note = null,
    ): Recommendation {
        if (! $recommendation->isOpen()) {
            throw ValidationException::withMessages([
                'follow_up_at' => 'Follow-up can only be snoozed on an open recommendation.',
            ]);
        }

        $when = $until instanceof Carbon ? $until : Carbon::parse($until);

        $recommendation->forceFill([
            'follow_up_snoozed_until' => $when,
            'follow_up_completed_at' => null,
        ])->save();

        $this->events->handle(
            $recommendation,
            RecommendationEventType::FollowUpSnoozed,
            actor: $actor,
            note: $note,
            payload: [
                'follow_up_snoozed_until' => $when->toIso8601String(),
            ],
        );

        return $recommendation->fresh() ?? $recommendation;
    }
}
