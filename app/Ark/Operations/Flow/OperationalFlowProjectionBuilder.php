<?php

namespace App\Ark\Operations\Flow;

use App\Ark\Operations\Attention\CustomerDecisionPressure;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class OperationalFlowProjectionBuilder
{
    private const VOLUME_WEIGHT = 0.25;

    private const AGE_WEIGHT = 0.30;

    private const REVENUE_WEIGHT = 0.30;

    private const SIGNAL_WEIGHT = 0.15;

    private const SEVENTY_TWO_HOURS_MINUTES = 72 * 60;

    public function __construct(
        private readonly FlowStageResolver $stageResolver,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly WorkboardTriageProjection $triageProjection,
        private readonly CustomerDecisionPressure $customerDecisionPressure,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     */
    public function build(Collection $openRepairOrders): OperationalFlowProjection
    {
        $generatedAt = now();
        $openRepairOrders = $openRepairOrders->values();

        foreach ($openRepairOrders as $repairOrder) {
            $repairOrder->loadMissing(['lines']);
        }

        $statusEnteredAt = $this->latestStatusEnteredAtByRepairOrderId($openRepairOrders);
        $triageCards = $this->triageProjection->allTriageCardsForRepairOrders($openRepairOrders);
        $triageCardsByShopRepairOrderId = collect($triageCards)
            ->keyBy(fn (WorkboardTriageCard $card): int => $card->repairOrder->repair_order_id);
        $decisionPressureShopRepairOrderIds = $this->decisionPressureShopRepairOrderIds();

        /** @var array<string, list<RepairOrder>> $repairOrdersByStage */
        $repairOrdersByStage = [];

        foreach (FlowStageKey::ordered() as $stageKey) {
            $repairOrdersByStage[$stageKey->value] = [];
        }

        foreach ($openRepairOrders as $repairOrder) {
            $stageKey = $this->stageResolver->stageKeyFor($repairOrder);

            if ($stageKey === null) {
                continue;
            }

            $repairOrdersByStage[$stageKey->value][] = $repairOrder;
        }

        /** @var array<string, array{count: int, oldest: int, median: int, revenue: int, signal: int, ages: list<int>}> $rawByStage */
        $rawByStage = [];

        foreach (FlowStageKey::ordered() as $stageKey) {
            /** @var list<RepairOrder> $stageRepairOrders */
            $stageRepairOrders = $repairOrdersByStage[$stageKey->value];
            $ages = [];

            foreach ($stageRepairOrders as $repairOrder) {
                $ages[] = $this->ageMinutes(
                    $statusEnteredAt[$repairOrder->id] ?? $repairOrder->updated_at ?? $repairOrder->created_at,
                    $generatedAt,
                );
            }

            $rawByStage[$stageKey->value] = [
                'count' => count($stageRepairOrders),
                'oldest' => $ages === [] ? 0 : max($ages),
                'median' => $this->median($ages),
                'revenue' => collect($stageRepairOrders)->sum(
                    fn (RepairOrder $repairOrder): int => $this->revenueCentsFor($repairOrder, $stageKey),
                ),
                'signal' => $this->signalScoreForStage(
                    $stageRepairOrders,
                    $triageCardsByShopRepairOrderId,
                    $decisionPressureShopRepairOrderIds,
                ),
                'ages' => $ages,
            ];
        }

        $activeStages = collect($rawByStage)->filter(fn (array $raw): bool => $raw['count'] > 0);

        $maxCount = (int) ($activeStages->max('count') ?? 0);
        $maxMedianAge = (int) ($activeStages->max('median') ?? 0);
        $maxRevenue = (int) ($activeStages->max('revenue') ?? 0);
        $maxSignal = (int) ($activeStages->max('signal') ?? 0);

        $stages = [];
        $bestConstraint = null;
        $bestPressureScore = -1;

        foreach (FlowStageKey::ordered() as $stageKey) {
            $raw = $rawByStage[$stageKey->value];
            $volumeNorm = $this->normalize($raw['count'], $maxCount);
            $ageNorm = $this->normalize($raw['median'], $maxMedianAge);
            $revenueNorm = $this->normalize($raw['revenue'], $maxRevenue);
            $signalNorm = $this->normalize($raw['signal'], $maxSignal);

            $pressureScore = (int) round(
                (self::VOLUME_WEIGHT * $volumeNorm)
                + (self::AGE_WEIGHT * $ageNorm)
                + (self::REVENUE_WEIGHT * $revenueNorm)
                + (self::SIGNAL_WEIGHT * $signalNorm),
            );

            $stageProjection = new FlowStageProjection(
                stageKey: $stageKey,
                label: $stageKey->label(),
                count: $raw['count'],
                oldestAgeMinutes: $raw['oldest'],
                oldestAgeLabel: $this->ageLabel($raw['oldest']),
                medianAgeMinutes: $raw['median'],
                medianAgeLabel: $this->ageLabel($raw['median']),
                revenueCents: $raw['revenue'],
                revenueLabel: $this->moneyLabel($raw['revenue']),
                pressureScore: $pressureScore,
                inventoryUrl: $stageKey->inventoryUrl(),
                signalSummary: $this->signalSummary($raw['ages'], $stageKey, $decisionPressureShopRepairOrderIds, $repairOrdersByStage[$stageKey->value]),
            );

            $stages[] = $stageProjection;

            if ($raw['count'] > 0 && $pressureScore > $bestPressureScore) {
                $bestPressureScore = $pressureScore;
                $bestConstraint = $this->constraintFor(
                    $stageKey,
                    $pressureScore,
                    $raw,
                    $volumeNorm,
                    $ageNorm,
                    $revenueNorm,
                    $signalNorm,
                );
            }
        }

        return new OperationalFlowProjection(
            stages: $stages,
            constraint: $bestConstraint,
            generatedAt: $generatedAt,
        );
    }

    /**
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     * @return array<int, Carbon>
     */
    private function latestStatusEnteredAtByRepairOrderId(Collection $openRepairOrders): array
    {
        if ($openRepairOrders->isEmpty()) {
            return [];
        }

        $events = OperationalEvent::query()
            ->where('aggregate_type', RepairOrder::class)
            ->whereIn('aggregate_id', $openRepairOrders->pluck('id'))
            ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->groupBy('aggregate_id');

        $enteredAt = [];

        foreach ($openRepairOrders as $repairOrder) {
            $statusSlug = $repairOrder->status->value;
            $matching = ($events->get($repairOrder->id) ?? collect())
                ->filter(fn (OperationalEvent $event): bool => ($event->payload_json['to_status'] ?? null) === $statusSlug)
                ->last();

            $enteredAt[$repairOrder->id] = $matching?->occurred_at
                ?? $repairOrder->updated_at
                ?? $repairOrder->opened_at
                ?? $repairOrder->created_at;
        }

        return $enteredAt;
    }

    /**
     * @return array<int, true>
     */
    private function decisionPressureShopRepairOrderIds(): array
    {
        $pressure = $this->customerDecisionPressure->resolve();
        $lookup = [];

        foreach (['estimate_ready_not_sent', 'customer_decision_needed', 'approved_work_stalled'] as $bucket) {
            foreach ($pressure[$bucket] ?? [] as $row) {
                $shopRepairOrderId = (int) ($row['repair_order_id'] ?? 0);

                if ($shopRepairOrderId > 0) {
                    $lookup[$shopRepairOrderId] = true;
                }
            }
        }

        return $lookup;
    }

    /**
     * @param  Collection<int, WorkboardTriageCard>  $triageCardsByShopRepairOrderId
     * @param  array<int, true>  $decisionPressureShopRepairOrderIds
     * @param  list<RepairOrder>  $stageRepairOrders
     */
    private function signalScoreForStage(
        array $stageRepairOrders,
        Collection $triageCardsByShopRepairOrderId,
        array $decisionPressureShopRepairOrderIds,
    ): int {
        $score = 0;

        foreach ($stageRepairOrders as $repairOrder) {
            $card = $triageCardsByShopRepairOrderId->get($repairOrder->repair_order_id);

            if ($card instanceof WorkboardTriageCard) {
                $score += min(50, $card->pressureScore);

                if ($card->signalTone === 'alert') {
                    $score += 25;
                } elseif ($card->signalTone === 'warn') {
                    $score += 12;
                }
            }

            if (isset($decisionPressureShopRepairOrderIds[$repairOrder->repair_order_id])) {
                $score += 30;
            }
        }

        return $score;
    }

    private function revenueCentsFor(RepairOrder $repairOrder, FlowStageKey $stageKey): int
    {
        $preApproval = in_array($stageKey, [
            FlowStageKey::WorkArrives,
            FlowStageKey::NeedsDiagnosis,
            FlowStageKey::BuildingEstimate,
            FlowStageKey::WaitingApproval,
        ], true);

        return $preApproval
            ? $this->totalsCalculator->totalsFor($repairOrder)->totalCents()
            : $this->totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();
    }

    /**
     * @param  list<int>  $ages
     * @param  array<int, true>  $decisionPressureShopRepairOrderIds
     * @param  list<RepairOrder>  $stageRepairOrders
     */
    private function signalSummary(
        array $ages,
        FlowStageKey $stageKey,
        array $decisionPressureShopRepairOrderIds,
        array $stageRepairOrders,
    ): ?string {
        if ($ages === []) {
            return null;
        }

        $overSeventyTwoHours = count(array_filter(
            $ages,
            fn (int $minutes): bool => $minutes >= self::SEVENTY_TWO_HOURS_MINUTES,
        ));

        $decisionPressureCount = count(array_filter(
            $stageRepairOrders,
            fn (RepairOrder $repairOrder): bool => isset($decisionPressureShopRepairOrderIds[$repairOrder->repair_order_id]),
        ));

        $parts = [];

        if ($overSeventyTwoHours > 0) {
            $parts[] = $overSeventyTwoHours.' over 72h';
        }

        if ($stageKey === FlowStageKey::WaitingApproval && $decisionPressureCount > 0) {
            $parts[] = $decisionPressureCount.' decision pressure';
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @param  array{count: int, oldest: int, median: int, revenue: int, signal: int, ages: list<int>}  $raw
     */
    private function constraintFor(
        FlowStageKey $stageKey,
        int $pressureScore,
        array $raw,
        int $volumeNorm,
        int $ageNorm,
        int $revenueNorm,
        int $signalNorm,
    ): FlowConstraintProjection {
        $norms = [
            'volume' => $volumeNorm,
            'age' => $ageNorm,
            'revenue' => $revenueNorm,
            'signal' => $signalNorm,
        ];

        arsort($norms);

        $reasons = [];

        foreach (array_keys($norms) as $component) {
            if (count($reasons) >= 3) {
                break;
            }

            $reason = match ($component) {
                'volume' => $volumeNorm > 0
                    ? 'Highest volume ('.$raw['count'].' RO'.($raw['count'] === 1 ? '' : 's').')'
                    : null,
                'age' => $ageNorm > 0
                    ? 'Oldest median age ('.$this->ageLabel($raw['median']).')'
                    : null,
                'revenue' => $revenueNorm > 0
                    ? 'Highest revenue trapped ('.$this->moneyLabel($raw['revenue']).')'
                    : null,
                'signal' => $signalNorm > 0
                    ? 'Strongest operational signals'
                    : null,
                default => null,
            };

            if ($reason !== null) {
                $reasons[] = $reason;
            }
        }

        return new FlowConstraintProjection(
            stageKey: $stageKey,
            label: $stageKey->label(),
            pressureScore: $pressureScore,
            headline: $this->constraintHeadline($stageKey),
            reasons: $reasons,
        );
    }

    private function constraintHeadline(FlowStageKey $stageKey): string
    {
        return match ($stageKey) {
            FlowStageKey::WaitingApproval,
            FlowStageKey::BuildingEstimate,
            FlowStageKey::ReadyPickup => 'Largest limiter of cash conversion',
            default => 'Largest limiter of shop throughput',
        };
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        sort($values);
        $middle = (int) floor((count($values) - 1) / 2);

        if (count($values) % 2 === 0) {
            return (int) round(($values[$middle] + $values[$middle + 1]) / 2);
        }

        return $values[$middle];
    }

    private function normalize(int $value, int $max): int
    {
        if ($max <= 0 || $value <= 0) {
            return 0;
        }

        return (int) round(($value / $max) * 100);
    }

    private function ageMinutes(?Carbon $enteredAt, Carbon $generatedAt): int
    {
        if ($enteredAt === null) {
            return 0;
        }

        return max(0, (int) $enteredAt->diffInMinutes($generatedAt));
    }

    private function ageLabel(int $ageMinutes): string
    {
        if ($ageMinutes <= 0) {
            return '—';
        }

        if ($ageMinutes >= 24 * 60) {
            $days = (int) floor($ageMinutes / (24 * 60));

            return $days.'d';
        }

        if ($ageMinutes >= 60) {
            $hours = (int) floor($ageMinutes / 60);

            return $hours.'h';
        }

        return $ageMinutes.'m';
    }

    private function moneyLabel(int $cents): string
    {
        if ($cents <= 0) {
            return '—';
        }

        return '$'.number_format($cents / 100, 0);
    }
}
