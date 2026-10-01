<?php

namespace App\Ark\Operations\Today\Lifecycle;

use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Today\Surface\TodayLens;

interface TodayRecommendationLifecycle
{
    public function kind(): TodayRecommendationKind;

    public function completionAuthority(): TodayCompletionAuthority;

    /**
     * @return list<TodayCompletionEvent>
     */
    public function completionEvents(): array;

    /**
     * @return list<TodayLifecycleCandidate>
     */
    public function candidates(BriefingContext $context, TodayLens $lens): array;

    public function retirementFromOperationalEvent(OperationalEvent $event): ?RecommendationResolutionRetirement;

    public function retireReason(TodayCompletionEvent $event): string;
}
