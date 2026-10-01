<?php

namespace App\Ark\Operations\Today\Lifecycle;

use Illuminate\Support\Carbon;

final readonly class TodayLifecycleCandidate
{
    public function __construct(
        public TodayRecommendationKind $kind,
        public string $instanceKey,
        public string $title,
        public string $ownerLabel,
        public string $url,
        public string $whyYouLabel,
        public string $expectedOutcome,
        public ?string $reason,
        public ?string $effortLabel,
        public string $aggregateType,
        public int $aggregateId,
        public Carbon $pressureSince,
        public int $sortWeight,
        public string $sectionKey,
    ) {}
}
