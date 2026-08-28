<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\Today\Lifecycle\RecommendationResolutionRetirement;
use App\Ark\Operations\Today\Lifecycle\TodayLifecycleRegistry;

final class RecommendationResolutionRecorder
{
    public function __construct(
        private readonly TodayLifecycleRegistry $registry,
    ) {}

    public function observeOperationalEvent(\App\Ark\Operations\Events\OperationalEvent $event): void
    {
        foreach ($this->registry->lifecycles() as $lifecycle) {
            $retirement = $lifecycle->retirementFromOperationalEvent($event);

            if ($retirement === null) {
                continue;
            }

            $this->record($retirement);
        }
    }

    public function record(RecommendationResolutionRetirement $retirement): RecommendationResolution
    {
        $elapsed = null;

        if ($retirement->pressureSince !== null) {
            $elapsed = max(0, (int) $retirement->pressureSince->diffInMinutes($retirement->completedAt));
        }

        $recentDuplicate = RecommendationResolution::query()
            ->where('recommendation_kind', $retirement->kind->value)
            ->where('aggregate_type', $retirement->aggregateType)
            ->where('aggregate_id', $retirement->aggregateId)
            ->where('completed_at', '>=', $retirement->completedAt->copy()->subMinutes(5))
            ->exists();

        if ($recentDuplicate) {
            return RecommendationResolution::query()
                ->where('recommendation_kind', $retirement->kind->value)
                ->where('aggregate_type', $retirement->aggregateType)
                ->where('aggregate_id', $retirement->aggregateId)
                ->latest('completed_at')
                ->firstOrFail();
        }

        return RecommendationResolution::query()->create([
            'recommendation_kind' => $retirement->kind->value,
            'aggregate_type' => $retirement->aggregateType,
            'aggregate_id' => $retirement->aggregateId,
            'completed_by_user_id' => $retirement->completedByUserId,
            'completion_event' => $retirement->completionEvent->value,
            'outcome_label' => $retirement->outcomeLabel,
            'title_snapshot' => $retirement->titleSnapshot,
            'pressure_since' => $retirement->pressureSince,
            'completed_at' => $retirement->completedAt,
            'elapsed_minutes' => $elapsed,
        ]);
    }
}
