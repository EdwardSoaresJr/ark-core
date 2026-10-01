<?php

namespace App\Ark\Operations\Today\Lifecycle;

use Illuminate\Support\Carbon;

final readonly class RecommendationResolutionRetirement
{
    public function __construct(
        public TodayRecommendationKind $kind,
        public string $aggregateType,
        public int $aggregateId,
        public ?int $completedByUserId,
        public TodayCompletionEvent $completionEvent,
        public string $outcomeLabel,
        public string $titleSnapshot,
        public Carbon $pressureSince,
        public Carbon $completedAt,
    ) {}
}
