<?php

namespace App\Ark\Operations\Briefing\Rules;

use App\Ark\Operations\Briefing\BriefingConfidence;
use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingEvidenceItem;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

final class LargeEstimateAgingBriefingRule implements BriefingRule
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totals,
    ) {}

    public function key(): string
    {
        return 'large_estimate_aging';
    }

    public function items(BriefingContext $context): array
    {
        $thresholdCents = (int) config('briefing.large_estimate_cents', 200_000);
        $agingDays = (int) config('briefing.estimate_aging_days', 3);
        $cutoff = now()->subDays($agingDays);

        $repairOrders = RepairOrder::query()
            ->with('customer')
            ->where('status', RepairOrderStatus::WaitingApproval)
            ->where('updated_at', '<', $cutoff)
            ->get()
            ->filter(function (RepairOrder $repairOrder) use ($thresholdCents): bool {
                return $this->totals->totalsFor($repairOrder)->totalCents() >= $thresholdCents;
            })
            ->take(5);

        $items = [];

        foreach ($repairOrders as $repairOrder) {
            $totalCents = $this->totals->totalsFor($repairOrder)->totalCents();
            $days = (int) $repairOrder->updated_at->diffInDays(now());

            $items[] = new BriefingItem(
                key: $this->key().'_'.$repairOrder->repair_order_id,
                headline: sprintf(
                    'Large estimate aging · $%s',
                    number_format($totalCents / 100, 0),
                ),
                summary: sprintf(
                    '%s · RO #%d · %d days in waiting approval',
                    $repairOrder->customer?->display_name ?? 'Customer',
                    $repairOrder->repair_order_id,
                    max(1, $days),
                ),
                priority: $days >= 5 ? BriefingPriority::Critical : BriefingPriority::High,
                confidence: new BriefingConfidence(
                    score: 86,
                    reason: 'Large estimate waiting approval beyond configured aging threshold.',
                    signals: [
                        ['label' => 'Estimate exceeds large threshold', 'satisfied' => true],
                        ['label' => sprintf('Waiting more than %d days', $agingDays), 'satisfied' => true],
                    ],
                    facts: [
                        ['label' => 'Repair order', 'value' => '#'.$repairOrder->repair_order_id],
                        ['label' => 'Estimate total', 'value' => '$'.number_format($totalCents / 100, 0)],
                    ],
                ),
                evidenceItems: [
                    new BriefingEvidenceItem(
                        sourceType: 'repair_order',
                        summary: 'Waiting approval',
                        occurredAt: $repairOrder->updated_at,
                        detail: $repairOrder->concern_summary,
                        sourceId: $repairOrder->repair_order_id,
                        sourceLabel: 'Repair order',
                    ),
                ],
                actionUrl: route('operations.repair-orders.show', $repairOrder),
                actionLabel: 'Review estimate',
                repairOrderId: $repairOrder->repair_order_id,
                customerId: $repairOrder->customer_id,
            );
        }

        return $items;
    }
}
