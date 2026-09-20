<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingRepository;
use App\Models\User;

final class TodayExplainabilityComposer
{
    public function __construct(
        private readonly BriefingRepository $briefing,
    ) {}

    /**
     * @return list<string>
     */
    public function lines(BriefingContext $context, User $user, TodayLens $lens): array
    {
        $items = $this->briefing->attentionItems($context);
        $lines = [];

        $waitingCount = $this->countByPrefix($items, 'customer_waiting');
        if ($waitingCount > 0) {
            $lines[] = $waitingCount === 1
                ? '1 customer is waiting on the shop.'
                : $waitingCount.' customers are waiting.';
        }

        $followUps = array_values(array_filter(
            $items,
            static fn (BriefingItem $item): bool => str_starts_with($item->key, 'estimate_follow_up'),
        ));

        if ($followUps !== []) {
            $maxViews = 0;
            foreach ($followUps as $item) {
                if (preg_match('/(\d+)×/', $item->headline, $matches)) {
                    $maxViews = max($maxViews, (int) $matches[1]);
                }
            }

            if ($maxViews > 0) {
                $lines[] = $maxViews === 1
                    ? '1 estimate has been viewed without follow-up.'
                    : '1 estimate has been viewed '.$maxViews.'×.';
            } else {
                $lines[] = count($followUps) === 1
                    ? '1 estimate needs follow-up.'
                    : count($followUps).' estimates need follow-up.';
            }
        }

        $partsWaiting = $this->countByPrefix($items, 'waiting_parts');
        if ($partsWaiting > 0) {
            $lines[] = $partsWaiting === 1
                ? '1 repair is waiting on parts.'
                : $partsWaiting.' repairs are waiting on parts.';
        }

        $missedCalls = $this->countByPrefix($items, 'missed_call');
        if ($missedCalls > 0) {
            $lines[] = $missedCalls === 1
                ? '1 missed call still needs handling.'
                : $missedCalls.' missed calls still need handling.';
        }

        $decisions = $this->countByPrefix($items, 'large_estimate_aging');
        if ($decisions > 0) {
            $lines[] = $decisions === 1
                ? '1 high-value estimate is waiting on a customer decision.'
                : $decisions.' high-value estimates are waiting on customer decisions.';
        }

        return $lines;
    }

    public function caughtUpFocus(TodayLens $lens): string
    {
        return match ($lens) {
            TodayLens::Owner => 'Keep the floor moving.',
            TodayLens::Advisor => 'Continue scheduled work.',
            TodayLens::Technician => 'Continue assigned production on the floor.',
        };
    }

    /**
     * @param  list<BriefingItem>  $items
     */
    private function countByPrefix(array $items, string $prefix): int
    {
        return count(array_filter(
            $items,
            static fn (BriefingItem $item): bool => str_starts_with($item->key, $prefix),
        ));
    }
}
