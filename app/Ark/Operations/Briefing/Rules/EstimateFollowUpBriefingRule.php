<?php

namespace App\Ark\Operations\Briefing\Rules;

use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Today\Lifecycle\EstimateFollowUpLifecycle;

final class EstimateFollowUpBriefingRule implements BriefingRule
{
    public function __construct(
        private readonly EstimateFollowUpLifecycle $lifecycle,
    ) {}

    public function key(): string
    {
        return 'estimate_follow_up';
    }

    public function items(BriefingContext $context): array
    {
        return $this->lifecycle->briefingItems($context);
    }
}
