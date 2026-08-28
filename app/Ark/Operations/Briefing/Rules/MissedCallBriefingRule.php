<?php

namespace App\Ark\Operations\Briefing\Rules;

use App\Ark\Operations\Briefing\BriefingConfidence;
use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingEvidenceItem;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;

final class MissedCallBriefingRule implements BriefingRule
{
    public function key(): string
    {
        return 'missed_call';
    }

    public function items(BriefingContext $context): array
    {
        $calls = CallSession::query()
            ->with('customer')
            ->where('status', CallSessionStatus::Missed)
            ->whereNull('worked_at')
            ->whereBetween('started_at', [$context->yesterdayFrom, $context->yesterdayTo])
            ->orderByDesc('started_at')
            ->limit(25)
            ->get();

        $items = [];

        foreach ($calls as $call) {
            $customerLabel = $call->customer?->display_name ?? $call->from_number ?? 'Unknown caller';

            $items[] = new BriefingItem(
                key: $this->key().'_'.$call->id,
                headline: 'Missed call from '.$customerLabel,
                summary: 'Call was not marked handled · '.$call->started_at?->format('M j g:i A'),
                priority: BriefingPriority::High,
                confidence: new BriefingConfidence(
                    score: 92,
                    reason: 'Missed call session with no handled timestamp.',
                    signals: [
                        ['label' => 'Call status is missed', 'satisfied' => true],
                        ['label' => 'Not marked handled', 'satisfied' => true],
                    ],
                    facts: [
                        ['label' => 'Call', 'value' => '#'.$call->id],
                        ['label' => 'From', 'value' => $call->from_number ?? '—'],
                    ],
                ),
                evidenceItems: [
                    new BriefingEvidenceItem(
                        sourceType: 'call_session',
                        summary: 'Missed inbound call',
                        occurredAt: $call->started_at ?? now(),
                        detail: $call->from_number,
                        sourceId: $call->id,
                        sourceLabel: 'Call',
                    ),
                ],
                actionUrl: CommunicationsNeedsYou::url(),
                actionLabel: 'Open Needs Attention',
                customerId: $call->customer_id,
            );
        }

        return $items;
    }
}
