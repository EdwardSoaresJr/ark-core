<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\Today\Surface\TodayOwnerResolver;

final class RecommendationHistoryProjection
{
    public function __construct(
        private readonly TodayOwnerResolver $owners,
    ) {}

    /**
     * @return list<array{title: string, outcome: string, elapsed_label: string|null, completed_by: string|null, completed_at_label: string}>
     */
    public function completedYesterday(int $limit = 5): array
    {
        return RecommendationResolution::query()
            ->with('completedBy')
            ->where('completed_at', '>=', now()->subDay())
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get()
            ->map(fn (RecommendationResolution $resolution): array => [
                'title' => $resolution->title_snapshot,
                'outcome' => $resolution->outcome_label,
                'elapsed_label' => $this->elapsedLabel($resolution->elapsed_minutes),
                'completed_by' => $resolution->completedBy !== null
                    ? $this->owners->firstName($resolution->completedBy)
                    : null,
                'completed_at_label' => $resolution->completed_at->format('g:i A'),
            ])
            ->all();
    }

    private function elapsedLabel(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes < 60) {
            return $minutes.' minute'.($minutes === 1 ? '' : 's');
        }

        $hours = intdiv($minutes, 60);

        return $hours.' hour'.($hours === 1 ? '' : 's');
    }
}
