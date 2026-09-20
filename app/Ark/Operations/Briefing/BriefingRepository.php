<?php

namespace App\Ark\Operations\Briefing;

use App\Ark\Operations\Briefing\Rules\BriefingRule;
use App\Ark\Operations\Briefing\Rules\CustomerWaitingResponseBriefingRule;
use App\Ark\Operations\Briefing\Rules\EstimateFollowUpBriefingRule;
use App\Ark\Operations\Briefing\Rules\LargeEstimateAgingBriefingRule;
use App\Ark\Operations\Briefing\Rules\MissedCallBriefingRule;
use App\Ark\Operations\Briefing\Rules\RevenueMovementBriefingRule;
use App\Ark\Operations\Briefing\Rules\WaitingPartsBriefingRule;

/**
 * Composes attention items from explainable briefing rules — owns no truth.
 */
final class BriefingRepository
{
    /** @var list<BriefingRule> */
    private array $rules;

    public function __construct(
        EstimateFollowUpBriefingRule $estimateFollowUp,
        MissedCallBriefingRule $missedCall,
        WaitingPartsBriefingRule $waitingParts,
        CustomerWaitingResponseBriefingRule $customerWaitingResponse,
        RevenueMovementBriefingRule $revenueMovement,
        LargeEstimateAgingBriefingRule $largeEstimateAging,
    ) {
        $this->rules = [
            $estimateFollowUp,
            $missedCall,
            $waitingParts,
            $customerWaitingResponse,
            $revenueMovement,
            $largeEstimateAging,
        ];
    }

    /**
     * @return list<BriefingItem>
     */
    public function attentionItems(BriefingContext $context): array
    {
        $items = [];

        foreach ($this->rules as $rule) {
            $items = array_merge($items, $rule->items($context));
        }

        usort(
            $items,
            static fn (BriefingItem $left, BriefingItem $right): int => $right->sortWeight() <=> $left->sortWeight(),
        );

        $maxItems = (int) config('briefing.max_attention_items', 12);

        return array_slice($items, 0, $maxItems);
    }
}
