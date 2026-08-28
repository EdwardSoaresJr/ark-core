<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Attention\CustomerDecisionPressure;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use Illuminate\Support\Collection;

/**
 * Deterministic, explainable recommendation ranking for advisor Today.
 *
 * Priority order is rule-based only — no LLM involvement.
 * One repair order = one recommendation; all signals aggregate into that card.
 */
final class AdvisorTodayRecommendationEngine
{
    private const RECOMMENDATION_LIMIT = 8;

    /** @var array<string, int> */
    private const RULE_BASE_SCORE = [
        'customer_decision_needed' => 100,
        'approved_work_stalled' => 95,
        'estimate_ready_not_sent' => 90,
        'multiple_customer_messages' => 88,
        'overdue_pickup' => 85,
        'estimate_viewed_multiple_times' => 82,
        'customer_waiting_response' => 78,
        'unassigned_tech' => 75,
        'parts_pressure' => 72,
        'vehicle_id_needed' => 70,
        'estimate_viewed' => 68,
    ];

    public function __construct(
        private readonly CustomerDecisionPressure $customerDecisionPressure,
        private readonly WorkboardTriageProjection $triageProjection,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return list<TodayRecommendation>
     */
    public function recommendations(Collection $repairOrders): array
    {
        $candidates = [];

        foreach ($this->decisionPressureRecommendations() as $recommendation) {
            $candidates[] = $recommendation;
        }

        foreach ($this->triageProjection->allTriageCardsForRepairOrders($repairOrders) as $card) {
            if (! $card->countsAsCustomerWaiting && ! $card->countsAsNeedsAttention) {
                continue;
            }

            $recommendation = $this->recommendationFromTriageCard($card);

            if ($recommendation !== null) {
                $candidates[] = $recommendation;
            }
        }

        return $this->aggregateRankAndLimit($candidates);
    }

    /**
     * @return list<TodayRecommendation>
     */
    private function decisionPressureRecommendations(): array
    {
        $pressure = $this->customerDecisionPressure->resolve();
        $recommendations = [];

        foreach ([
            'customer_decision_needed' => [
                'titleSuffix' => 'Customer decision needed',
                'impactKind' => TodayImpactKind::Revenue,
                'suggestedAction' => 'Follow up on estimate approval',
            ],
            'approved_work_stalled' => [
                'titleSuffix' => 'Approved work stalled',
                'impactKind' => TodayImpactKind::Production,
                'suggestedAction' => 'Move approved work into production',
            ],
            'estimate_ready_not_sent' => [
                'titleSuffix' => 'Estimate ready · not sent',
                'impactKind' => TodayImpactKind::Revenue,
                'suggestedAction' => 'Send estimate to customer',
            ],
        ] as $kind => $meta) {
            foreach (array_slice($pressure[$kind] ?? [], 0, 5) as $row) {
                $customerName = (string) ($row['customer_name'] ?? 'Customer');

                $recommendations[] = new TodayRecommendation(
                    ruleKey: $kind,
                    rankScore: $this->rankScore(
                        $kind,
                        ageDays: (int) ($row['age_days'] ?? 0),
                        dollarsCents: (int) ($row['dollars_at_risk_cents'] ?? 0),
                    ),
                    title: $this->titleForRule($kind, $customerName),
                    whyReasons: array_values(array_filter([
                        $meta['titleSuffix'],
                        $row['detail'] ?? null,
                        isset($row['dollars_at_risk_label']) ? ($row['dollars_at_risk_label'].' waiting') : null,
                        isset($row['age_label']) ? 'Open '.$row['age_label'] : null,
                        $row['last_customer_activity'] ?? null,
                    ])),
                    impactKind: $meta['impactKind'],
                    impactLabel: $this->impactLabel(
                        $meta['impactKind'],
                        (int) ($row['dollars_at_risk_cents'] ?? 0),
                        $meta['titleSuffix'],
                    ),
                    suggestedAction: $meta['suggestedAction'],
                    repairOrderId: (int) $row['repair_order_id'],
                    repairOrderUrl: (string) ($row['url'] ?? '#'),
                    textUrl: $row['text_url'] ?? null,
                    callUrl: $this->callUrl($row['callback_phone'] ?? null),
                    customerName: $customerName,
                );
            }
        }

        return $recommendations;
    }

    private function recommendationFromTriageCard(WorkboardTriageCard $card): ?TodayRecommendation
    {
        $ruleKey = $this->ruleKeyForCard($card);

        if ($ruleKey === null) {
            return null;
        }

        $whyReasons = array_values(array_filter([
            $card->signalLabel,
            $card->concernHeadline !== 'No concern recorded' ? $card->concernHeadline : null,
            $card->ageLabel !== '' ? 'Updated '.$card->ageLabel.' ago' : null,
        ]));

        if ($whyReasons === []) {
            return null;
        }

        [$impactKind, $suggestedAction] = $this->impactAndActionForRule($ruleKey);
        $customerName = $this->customerNameForRepairOrder($card->repairOrder);

        return new TodayRecommendation(
            ruleKey: $ruleKey,
            rankScore: $this->rankScore(
                $ruleKey,
                ageMinutes: $card->ageMinutes,
                pressureScore: $card->pressureScore,
            ),
            title: $this->titleForRule($ruleKey, $customerName),
            whyReasons: $whyReasons,
            impactKind: $impactKind,
            impactLabel: $this->impactLabelForRule($ruleKey, $card),
            suggestedAction: $suggestedAction,
            repairOrderId: $card->repairOrder->repair_order_id,
            repairOrderUrl: $card->href,
            textUrl: $this->textUrlForRepairOrder($card->repairOrder),
            callUrl: $this->callUrl($card->repairOrder->customer?->phone),
            customerName: $customerName,
        );
    }

    private function ruleKeyForCard(WorkboardTriageCard $card): ?string
    {
        return match ($card->signalLabel) {
            'Multiple Customer Messages' => 'multiple_customer_messages',
            'Overdue Pickup' => 'overdue_pickup',
            'Estimate Viewed Multiple Times' => 'estimate_viewed_multiple_times',
            'Estimate Viewed' => 'estimate_viewed',
            'Customer Waiting', 'Customer waiting response' => 'customer_waiting_response',
            'Unassigned Tech' => 'unassigned_tech',
            'Vehicle ID Needed' => 'vehicle_id_needed',
            default => $card->countsAsOverduePickup
                ? 'overdue_pickup'
                : ($card->signalTone === 'warn' || $card->signalTone === 'alert'
                    ? 'parts_pressure'
                    : null),
        };
    }

    /**
     * @return array{0: TodayImpactKind, 1: string}
     */
    private function impactAndActionForRule(string $ruleKey): array
    {
        return match ($ruleKey) {
            'multiple_customer_messages', 'customer_waiting_response', 'estimate_viewed', 'estimate_viewed_multiple_times' => [
                TodayImpactKind::CustomerTrust,
                'Reply to customer thread',
            ],
            'customer_decision_needed', 'estimate_ready_not_sent' => [
                TodayImpactKind::Revenue,
                'Follow up on estimate approval',
            ],
            'overdue_pickup' => [
                TodayImpactKind::Revenue,
                'Contact customer about pickup',
            ],
            'approved_work_stalled' => [
                TodayImpactKind::Production,
                'Move approved work into production',
            ],
            'unassigned_tech', 'parts_pressure', 'vehicle_id_needed' => [
                TodayImpactKind::Production,
                'Clear production blocker on this RO',
            ],
            default => [
                TodayImpactKind::CustomerTrust,
                'Review repair order',
            ],
        };
    }

    private function impactLabelForRule(string $ruleKey, WorkboardTriageCard $card): string
    {
        return match ($ruleKey) {
            'overdue_pickup' => 'Cash and bay space tied up until pickup',
            'multiple_customer_messages' => 'Customer trust drops when messages go unanswered',
            'estimate_viewed_multiple_times' => 'High-intent estimate review — approval may be close',
            'estimate_viewed' => 'Customer is reviewing estimate',
            'unassigned_tech' => 'Shop floor work cannot start without a tech',
            'parts_pressure' => ($card->signalLabel ?? 'Parts').' is slowing production',
            'vehicle_id_needed' => 'Vehicle identity blocks accurate workflow',
            default => TodayImpactKind::CustomerTrust->label().' — respond before momentum fades',
        };
    }

    private function impactLabel(TodayImpactKind $kind, int $dollarsCents, string $fallback): string
    {
        if ($kind === TodayImpactKind::Revenue && $dollarsCents > 0) {
            return '$'.number_format($dollarsCents / 100, 0).' at risk';
        }

        return match ($kind) {
            TodayImpactKind::Revenue => 'Revenue waiting on customer action',
            TodayImpactKind::Production => 'Production blocked on approved work',
            TodayImpactKind::CustomerTrust => $fallback,
        };
    }

    private function rankScore(
        string $ruleKey,
        int $ageDays = 0,
        int $ageMinutes = 0,
        int $dollarsCents = 0,
        int $pressureScore = 0,
    ): int {
        $base = self::RULE_BASE_SCORE[$ruleKey] ?? 50;
        $ageBonus = $ageDays > 0
            ? min(20, $ageDays * 2)
            : min(12, (int) floor($ageMinutes / 60));
        $dollarBonus = min(25, (int) floor($dollarsCents / 10000));
        $pressureBonus = min(15, (int) floor($pressureScore / 4));

        return $base + $ageBonus + $dollarBonus + $pressureBonus;
    }

    /**
     * @param  list<TodayRecommendation>  $candidates
     * @return list<TodayRecommendation>
     */
    private function aggregateRankAndLimit(array $candidates): array
    {
        /** @var array<int, list<TodayRecommendation>> $byRepairOrder */
        $byRepairOrder = [];

        foreach ($candidates as $candidate) {
            $byRepairOrder[$candidate->repairOrderId][] = $candidate;
        }

        $merged = [];

        foreach ($byRepairOrder as $group) {
            $merged[] = $this->mergeRecommendationsForRepairOrder($group);
        }

        return collect($merged)
            ->sortByDesc(fn (TodayRecommendation $recommendation): int => $recommendation->rankScore)
            ->take(self::RECOMMENDATION_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  list<TodayRecommendation>  $candidates
     */
    private function mergeRecommendationsForRepairOrder(array $candidates): TodayRecommendation
    {
        usort(
            $candidates,
            fn (TodayRecommendation $left, TodayRecommendation $right): int => $right->rankScore <=> $left->rankScore,
        );

        $primary = $candidates[0];
        $whyReasons = [];

        foreach ($candidates as $candidate) {
            foreach ($candidate->whyReasons as $reason) {
                $whyReasons[] = $reason;
            }
        }

        $whyReasons = array_values(array_unique($whyReasons));
        $signalCount = count($candidates);
        $rankScore = $primary->rankScore + min(10, max(0, $signalCount - 1) * 3);

        $textUrl = collect($candidates)->pluck('textUrl')->filter()->first();
        $callUrl = collect($candidates)->pluck('callUrl')->filter()->first();

        $impactKind = $primary->impactKind;
        $impactLabel = $primary->impactLabel;

        foreach ($candidates as $candidate) {
            if ($candidate->impactKind === TodayImpactKind::Revenue && $candidate->impactLabel !== '') {
                $impactKind = TodayImpactKind::Revenue;
                $impactLabel = $candidate->impactLabel;

                break;
            }
        }

        return new TodayRecommendation(
            ruleKey: $primary->ruleKey,
            rankScore: $rankScore,
            title: $this->titleForRule($primary->ruleKey, $primary->customerName),
            whyReasons: $whyReasons,
            impactKind: $impactKind,
            impactLabel: $impactLabel,
            suggestedAction: $primary->suggestedAction,
            repairOrderId: $primary->repairOrderId,
            repairOrderUrl: $primary->repairOrderUrl,
            textUrl: is_string($textUrl) ? $textUrl : null,
            callUrl: is_string($callUrl) ? $callUrl : null,
            customerName: $primary->customerName,
        );
    }

    private function titleForRule(string $ruleKey, string $customerName): string
    {
        $verb = match ($ruleKey) {
            'customer_decision_needed' => 'Follow up with',
            'approved_work_stalled' => 'Move production for',
            'estimate_ready_not_sent' => 'Send estimate to',
            'multiple_customer_messages', 'customer_waiting_response' => 'Reply to',
            'estimate_viewed', 'estimate_viewed_multiple_times' => 'Call',
            'overdue_pickup' => 'Contact',
            'unassigned_tech', 'parts_pressure', 'vehicle_id_needed' => 'Clear blocker for',
            default => 'Review RO for',
        };

        return trim($verb.' '.$customerName);
    }

    private function customerNameForRepairOrder(RepairOrder $repairOrder): string
    {
        $name = trim(collect([
            $repairOrder->customer?->first_name,
            $repairOrder->customer?->last_name,
        ])->filter()->implode(' '));

        return $name !== '' ? $name : 'Customer';
    }

    private function textUrlForRepairOrder(RepairOrder $repairOrder): ?string
    {
        if ($repairOrder->customer_id === null) {
            return null;
        }

        return route('operations.customers.show', $repairOrder->customer_id).'?compose=text#customer-communication';
    }

    private function callUrl(?string $phone): ?string
    {
        $normalized = PhoneNumber::normalize($phone);

        return $normalized !== null && $normalized !== ''
            ? 'tel:'.$normalized
            : null;
    }
}
