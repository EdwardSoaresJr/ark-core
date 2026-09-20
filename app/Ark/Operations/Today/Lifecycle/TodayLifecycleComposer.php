<?php

namespace App\Ark\Operations\Today\Lifecycle;

use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Today\Surface\TodayAction;
use App\Ark\Operations\Today\Surface\TodayLens;

final class TodayLifecycleComposer
{
    public function __construct(
        private readonly TodayLifecycleRegistry $registry,
    ) {}

    /**
     * @return array<string, list<TodayAction>>
     */
    public function actionsBySection(BriefingContext $context, TodayLens $lens): array
    {
        $grouped = [];

        foreach ($this->registry->lifecycles() as $lifecycle) {
            foreach ($lifecycle->candidates($context, $lens) as $candidate) {
                $grouped[$candidate->sectionKey] ??= [];
                $grouped[$candidate->sectionKey][] = $this->toAction($candidate);
            }
        }

        return $grouped;
    }

    private function toAction(TodayLifecycleCandidate $candidate): TodayAction
    {
        return new TodayAction(
            key: $candidate->instanceKey,
            title: $candidate->title,
            ownerLabel: $candidate->ownerLabel,
            url: $candidate->url,
            whyYouLabel: $candidate->whyYouLabel,
            expectedOutcome: $candidate->expectedOutcome,
            effortLabel: $candidate->effortLabel,
            reason: $candidate->reason,
            recommendationKind: $candidate->kind->value,
        );
    }
}
