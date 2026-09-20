<?php

namespace App\Ark\Operations\Briefing\Rules;

use App\Ark\Operations\Briefing\BriefingConfidence;
use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingEvidenceItem;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\ShopBehaviorPulse;

final class WaitingPartsBriefingRule implements BriefingRule
{
    public function __construct(
        private readonly ShopBehaviorPulse $pulse,
    ) {}

    public function key(): string
    {
        return 'waiting_parts';
    }

    public function items(BriefingContext $context): array
    {
        $thresholdDays = (int) config('briefing.parts_waiting_days', 2);
        $threshold = now()->subDays($thresholdDays);

        $repairOrders = RepairOrder::query()
            ->with('customer')
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->get()
            ->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->workboardLaneStatus()->is(RepairOrderStatus::WaitingParts)
                && $repairOrder->updated_at->lt($threshold));

        if ($repairOrders->isEmpty()) {
            return [];
        }

        $pulseCount = collect($this->pulse->priorities())
            ->firstWhere('label', 'Parts backlog')['count'] ?? $repairOrders->count();

        $items = [];

        foreach ($repairOrders->take(5) as $repairOrder) {
            $days = (int) $repairOrder->updated_at->diffInDays(now());

            $items[] = new BriefingItem(
                key: $this->key().'_'.$repairOrder->repair_order_id,
                headline: 'Repair waiting on parts · RO #'.$repairOrder->repair_order_id,
                summary: sprintf(
                    '%s · waiting %d days',
                    $repairOrder->customer?->display_name ?? 'Customer',
                    max(1, $days),
                ),
                priority: $days >= 3 ? BriefingPriority::High : BriefingPriority::Normal,
                confidence: new BriefingConfidence(
                    score: 88,
                    reason: 'Repair order is in waiting-parts posture beyond shop threshold.',
                    signals: [
                        ['label' => 'Parts backlog posture', 'satisfied' => true],
                        ['label' => sprintf('Waiting longer than %d days', $thresholdDays), 'satisfied' => true],
                    ],
                    facts: [
                        ['label' => 'Repair order', 'value' => '#'.$repairOrder->repair_order_id],
                        ['label' => 'Shop parts backlog', 'value' => (string) $pulseCount],
                    ],
                ),
                evidenceItems: [
                    new BriefingEvidenceItem(
                        sourceType: 'repair_order',
                        summary: 'Waiting on parts',
                        occurredAt: $repairOrder->updated_at,
                        detail: $repairOrder->concern_summary,
                        sourceId: $repairOrder->repair_order_id,
                        sourceLabel: 'Repair order',
                    ),
                ],
                actionUrl: route('operations.repair-orders.show', $repairOrder),
                actionLabel: 'Open repair order',
                repairOrderId: $repairOrder->repair_order_id,
                customerId: $repairOrder->customer_id,
            );
        }

        return $items;
    }
}
