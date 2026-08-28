<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Flow\FlowStageKey;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageLaneProjection;
use Illuminate\Support\Collection;

/**
 * Fuses Flow, Pipeline, and Recommendations into one advisor home cockpit.
 */
final readonly class AdvisorHomeCockpitProjection
{
    public const HOT_CARD_LIMIT = 5;

    /**
     * @param  array<string, AdvisorHomeColumnStat>  $columnsByKey
     * @param  list<AdvisorHomeHotCard>  $hotCards
     * @param  list<AdvisorHomeAttentionZone>  $attentionZones
     */
    public function __construct(
        public ?string $constraintColumnKey,
        public ?string $constraintLabel,
        public ?AdvisorHomeColumnStat $constraintColumn,
        public int $pipelineAmountCents,
        public string $pipelineAmountLabel,
        public int $pipelineRoCount,
        public ?string $pipelineInventoryUrl,
        public ?TodayRecommendation $nextRecommendation,
        public ?int $recommendedRepairOrderId,
        public array $columnsByKey,
        public array $hotCards,
        public int $activeCarCount,
        public int $needsActionCount,
        public ?AdvisorHomeCockpitHighlight $largestPendingApproval,
        public ?AdvisorHomeCockpitHighlight $oldestOpenRo,
        public int $needsCallCount,
        public array $attentionZones,
        public ?AdvisorHomeAttentionRow $nextAttentionRow,
    ) {}

    /**
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @param  Collection<int, EstimateTotals>  $repairOrderTotals
     */
    /**
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @param  list<AdvisorHomeAttentionZone>  $attentionZones
     */
    public static function resolve(
        AdvisorTodayProjection $morningBrief,
        array $homeBoardColumns,
        Collection $repairOrderTotals,
        array $attentionZones,
    ): self {
        $columnsByKey = [];

        foreach ($homeBoardColumns as $column) {
            $amountCents = 0;

            foreach ($column->visibleCards as $card) {
                if (! $card instanceof WorkboardTriageCard) {
                    continue;
                }

                $totals = $repairOrderTotals[$card->repairOrder->id] ?? null;

                if ($totals instanceof EstimateTotals) {
                    $amountCents += $totals->totalCents();
                }
            }

            $columnsByKey[$column->key] = new AdvisorHomeColumnStat(
                key: $column->key,
                label: $column->label,
                count: $column->totalCount,
                amountCents: $amountCents,
                amountLabel: self::moneyLabel($amountCents),
            );
        }

        $constraint = $morningBrief->flow->constraint;
        $constraintColumnKey = $constraint !== null
            ? self::homeColumnKeyForStage($constraint->stageKey)
            : null;
        $constraintColumn = $constraintColumnKey !== null
            ? ($columnsByKey[$constraintColumnKey] ?? null)
            : null;

        $pipelineMetric = collect($morningBrief->pipeline)
            ->first(fn (TodayPipelineMetric $metric): bool => $metric->key === TodayPipelineInventoryQuery::REVENUE_IN_FLIGHT);

        $nextRecommendation = $morningBrief->briefRecommendations()[0] ?? null;
        $recommendedRepairOrderId = $nextRecommendation?->repairOrderId;

        $hotCards = self::hotCards(
            $homeBoardColumns,
            $repairOrderTotals,
            $recommendedRepairOrderId,
        );

        $needsActionCount = collect($attentionZones)
            ->first(fn (AdvisorHomeAttentionZone $zone): bool => $zone->key === AdvisorHomeAttentionZoneKey::NeedsAction)
            ?->count ?? 0;

        $allRows = collect($attentionZones)
            ->flatMap(fn (AdvisorHomeAttentionZone $zone): array => $zone->rows)
            ->values();

        $nextAttentionRow = $recommendedRepairOrderId !== null
            ? $allRows->first(fn (AdvisorHomeAttentionRow $row): bool => $row->repairOrderId === $recommendedRepairOrderId)
            : null;

        return new self(
            constraintColumnKey: $constraintColumnKey,
            constraintLabel: $constraint?->label,
            constraintColumn: $constraintColumn,
            pipelineAmountCents: $pipelineMetric?->amountCents ?? 0,
            pipelineAmountLabel: $pipelineMetric?->amountLabel ?? '$0',
            pipelineRoCount: $pipelineMetric?->repairOrderCount ?? 0,
            pipelineInventoryUrl: $pipelineMetric?->inventoryUrl,
            nextRecommendation: $nextRecommendation,
            recommendedRepairOrderId: $recommendedRepairOrderId,
            columnsByKey: $columnsByKey,
            hotCards: $hotCards,
            activeCarCount: $morningBrief->openRepairOrderCount,
            needsActionCount: $needsActionCount,
            largestPendingApproval: self::largestPendingApproval($allRows),
            oldestOpenRo: self::oldestOpenRo($allRows),
            needsCallCount: $morningBrief->commitments->dueTodayCount + $morningBrief->commitments->overdueCount,
            attentionZones: $attentionZones,
            nextAttentionRow: $nextAttentionRow instanceof AdvisorHomeAttentionRow ? $nextAttentionRow : null,
        );
    }

    /**
     * @param  Collection<int, AdvisorHomeAttentionRow>  $rows
     */
    private static function largestPendingApproval(Collection $rows): ?AdvisorHomeCockpitHighlight
    {
        $candidate = $rows
            ->filter(fn (AdvisorHomeAttentionRow $row): bool => str_contains(strtolower($row->statusBadge), 'approval')
                || str_contains(strtolower($row->statusBadge), 'estimate'))
            ->sortByDesc(fn (AdvisorHomeAttentionRow $row): int => $row->totalCents)
            ->first();

        if (! $candidate instanceof AdvisorHomeAttentionRow || $candidate->totalCents <= 0) {
            return null;
        }

        return new AdvisorHomeCockpitHighlight(
            label: $candidate->customerName,
            href: '#ops-card-ro-'.$candidate->repairOrderId,
            metaLabel: $candidate->totalLabel,
        );
    }

    /**
     * @param  Collection<int, AdvisorHomeAttentionRow>  $rows
     */
    private static function oldestOpenRo(Collection $rows): ?AdvisorHomeCockpitHighlight
    {
        $candidate = $rows
            ->sortByDesc(fn (AdvisorHomeAttentionRow $row): int => $row->ageDays)
            ->first();

        if (! $candidate instanceof AdvisorHomeAttentionRow || $candidate->ageDays < 1) {
            return null;
        }

        return new AdvisorHomeCockpitHighlight(
            label: $candidate->customerName,
            href: '#ops-card-ro-'.$candidate->repairOrderId,
            metaLabel: $candidate->ageLabel,
        );
    }

    public static function homeColumnKeyForStage(FlowStageKey $stageKey): ?string
    {
        return match ($stageKey) {
            FlowStageKey::NeedsDiagnosis,
            FlowStageKey::BuildingEstimate => 'estimates',
            FlowStageKey::WaitingApproval => 'waiting_approval',
            FlowStageKey::WaitingParts => 'parts',
            FlowStageKey::InRepair,
            FlowStageKey::QualityCheck => 'work_in_progress',
            FlowStageKey::ReadyPickup => 'completed',
            default => null,
        };
    }

    /**
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @param  Collection<int, EstimateTotals>  $repairOrderTotals
     * @return list<AdvisorHomeHotCard>
     */
    private static function hotCards(
        array $homeBoardColumns,
        Collection $repairOrderTotals,
        ?int $recommendedRepairOrderId,
    ): array {
        $candidates = [];

        foreach ($homeBoardColumns as $column) {
            foreach ($column->visibleCards as $card) {
                if (! $card instanceof WorkboardTriageCard) {
                    continue;
                }

                $repairOrder = $card->repairOrder;
                $totals = $repairOrderTotals[$repairOrder->id] ?? null;
                $totalCents = $totals instanceof EstimateTotals ? $totals->totalCents() : 0;
                $totalLabel = $totalCents > 0 && $totals instanceof EstimateTotals
                    ? $totals->format($totalCents)
                    : '—';
                $urgencyScore = $card->homeUrgencyScore($totalCents);
                $isRecommended = $recommendedRepairOrderId !== null
                    && $recommendedRepairOrderId === $repairOrder->repair_order_id;

                if (! $isRecommended
                    && ! $card->countsAsNeedsAttention
                    && $urgencyScore < 8
                    && $totalCents < 150_000) {
                    continue;
                }

                $candidates[] = new AdvisorHomeHotCard(
                    repairOrderId: $repairOrder->repair_order_id,
                    vehicleLabel: $card->vehicleLabel,
                    columnKey: $column->key,
                    columnLabel: $column->label,
                    actionStatement: $card->homeActionStatement($totalCents),
                    totalLabel: $totalLabel,
                    totalCents: $totalCents,
                    urgencyScore: $urgencyScore,
                    urgencyTier: $card->homeUrgencyTier($totalCents),
                    href: $card->href,
                    isRecommended: $isRecommended,
                );
            }
        }

        usort(
            $candidates,
            fn (AdvisorHomeHotCard $left, AdvisorHomeHotCard $right): int => $right->urgencyScore <=> $left->urgencyScore
                ?: $right->totalCents <=> $left->totalCents,
        );

        return array_slice($candidates, 0, self::HOT_CARD_LIMIT);
    }

    private static function moneyLabel(int $cents): string
    {
        if ($cents <= 0) {
            return '$0';
        }

        return '$'.number_format($cents / 100, 0);
    }
}
