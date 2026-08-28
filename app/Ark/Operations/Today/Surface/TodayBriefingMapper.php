<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingRepository;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use Illuminate\Support\Str;

final class TodayBriefingMapper
{
    public function __construct(
        private readonly BriefingRepository $briefing,
        private readonly TodayOwnerResolver $owners,
    ) {}

    /**
     * @param  list<string>  $ruleKeyPrefixes
     * @return list<BriefingItem>
     */
    public function items(BriefingContext $context, array $ruleKeyPrefixes, int $limit = 4): array
    {
        $items = array_values(array_filter(
            $this->briefing->attentionItems($context),
            function (BriefingItem $item) use ($ruleKeyPrefixes): bool {
                foreach ($ruleKeyPrefixes as $prefix) {
                    if (str_starts_with($item->key, $prefix)) {
                        return true;
                    }
                }

                return false;
            },
        ));

        return array_slice($items, 0, $limit);
    }

    public function toAction(BriefingItem $item, string $ownerLabel, ?string $title = null): TodayAction
    {
        $repairOrder = filled($item->repairOrderId)
            ? RepairOrder::query()->with('assignedTechnician')->find($item->repairOrderId)
            : null;

        return new TodayAction(
            key: $item->key,
            title: $title ?? $this->shortTitle($item),
            ownerLabel: $ownerLabel !== '' ? $ownerLabel : $this->owners->forRepairOrder($repairOrder),
            url: (string) ($item->actionUrl ?? CommunicationsNeedsYou::url()),
            whyYouLabel: $this->whyYouLabel($item, $repairOrder),
            expectedOutcome: $this->expectedOutcome($item),
            reason: $this->humanReason($item),
            detail: $item->summary !== '' ? $item->summary : null,
        );
    }

    private function shortTitle(BriefingItem $item): string
    {
        if (str_starts_with($item->key, 'customer_waiting')) {
            return Str::before($item->headline, ' is waiting') !== $item->headline
                ? Str::before($item->headline, ' is waiting').' — waiting for reply'
                : $item->headline;
        }

        if (str_starts_with($item->key, 'estimate_follow_up')) {
            return 'Follow up estimate';
        }

        if (str_starts_with($item->key, 'waiting_parts')) {
            return 'Waiting on parts';
        }

        if (str_starts_with($item->key, 'large_estimate_aging')) {
            return 'Customer decision needed';
        }

        if (str_starts_with($item->key, 'missed_call')) {
            return 'Missed call — call back';
        }

        return $item->headline;
    }

    private function humanReason(BriefingItem $item): ?string
    {
        if (str_starts_with($item->key, 'estimate_follow_up')) {
            if (preg_match('/(\d+)×/', $item->headline, $matches)) {
                return 'Estimate viewed '.$matches[1].'× without follow-up';
            }
        }

        if (str_starts_with($item->key, 'customer_waiting')) {
            return filled($item->summary) ? $item->summary : 'Customer is waiting on the shop';
        }

        if (str_starts_with($item->key, 'waiting_parts')) {
            if (preg_match('/waiting (\d+) days/', $item->summary, $matches)) {
                return 'Waiting '.$matches[1].' days on parts';
            }
        }

        if (str_starts_with($item->key, 'missed_call')) {
            return 'Not marked handled';
        }

        return filled($item->summary) ? $item->summary : null;
    }

    private function whyYouLabel(BriefingItem $item, ?RepairOrder $repairOrder): string
    {
        return match (true) {
            str_starts_with($item->key, 'estimate_follow_up'),
            str_starts_with($item->key, 'large_estimate_aging') => 'You own estimate follow-up.',
            str_starts_with($item->key, 'customer_waiting') => 'You are the assigned advisor.',
            str_starts_with($item->key, 'waiting_parts') => 'You coordinate parts and scheduling.',
            str_starts_with($item->key, 'missed_call') => 'You cover incoming customer calls.',
            default => 'You are responsible for this lane today.',
        };
    }

    private function expectedOutcome(BriefingItem $item): string
    {
        return match (true) {
            str_starts_with($item->key, 'estimate_follow_up') => 'Increase approval likelihood.',
            str_starts_with($item->key, 'large_estimate_aging') => 'Move a high-value decision forward.',
            str_starts_with($item->key, 'customer_waiting') => 'Restore customer confidence and momentum.',
            str_starts_with($item->key, 'waiting_parts') => 'Keep the repair moving once parts land.',
            str_starts_with($item->key, 'missed_call') => 'Recover a customer who tried to reach the shop.',
            default => 'Clear operational pressure before it stalls the day.',
        };
    }
}
