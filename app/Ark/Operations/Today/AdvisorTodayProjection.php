<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Flow\OperationalFlowProjection;
use App\Ark\Operations\Flow\OperationalFlowProjectionBuilder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class AdvisorTodayProjection
{
    public const BRIEF_RECOMMENDATION_LIMIT = 5;

    /**
     * @param  list<TodayRecommendation>  $recommendations
     * @param  list<TodayWorkQueueRow>  $workQueues
     * @param  list<TodayPipelineMetric>  $pipeline
     */
    public function __construct(
        public OperationalFlowProjection $flow,
        public array $recommendations,
        public array $workQueues,
        public array $pipeline,
        public TodayCommitmentsSummary $commitments,
        public int $openRepairOrderCount,
    ) {}

    /**
     * @return list<TodayRecommendation>
     */
    public function briefRecommendations(): array
    {
        return array_slice($this->recommendations, 0, self::BRIEF_RECOMMENDATION_LIMIT);
    }

    public function additionalRecommendationCount(): int
    {
        return max(0, count($this->recommendations) - self::BRIEF_RECOMMENDATION_LIMIT);
    }

    public static function resolve(
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        AdvisorTodayRecommendationEngine $recommendationEngine,
        AdvisorTodayShopRadarBuilder $shopRadarBuilder,
        TodayPipelineProjection $pipelineProjection,
        TodayCommitmentsProjection $commitmentsProjection,
        OperationalFlowProjectionBuilder $flowProjectionBuilder,
        ?User $actor = null,
        ?TodayRecommendationSnoozeResolver $snoozeResolver = null,
        ?Collection $repairOrders = null,
    ): self {
        $repairOrders ??= $repairOrderQuery->forAdvisor();
        $recommendations = $recommendationEngine->recommendations($repairOrders);

        if ($actor !== null) {
            $snoozedShopRepairOrderIds = ($snoozeResolver ?? app(TodayRecommendationSnoozeResolver::class))
                ->activeShopRepairOrderIds($actor);

            if ($snoozedShopRepairOrderIds !== []) {
                $snoozedLookup = array_fill_keys($snoozedShopRepairOrderIds, true);
                $recommendations = array_values(array_filter(
                    $recommendations,
                    fn (TodayRecommendation $recommendation): bool => ! isset($snoozedLookup[$recommendation->repairOrderId]),
                ));
            }

            $recommendations = self::withCloseLostEligibility($recommendations, $actor);
        }

        return new self(
            flow: $flowProjectionBuilder->build($repairOrders),
            recommendations: $recommendations,
            workQueues: $shopRadarBuilder->workQueueRows($repairOrders),
            pipeline: $pipelineProjection->metrics(),
            commitments: $commitmentsProjection->summary(),
            openRepairOrderCount: $repairOrders->count(),
        );
    }

    /**
     * @param  list<TodayRecommendation>  $recommendations
     * @return list<TodayRecommendation>
     */
    private static function withCloseLostEligibility(array $recommendations, User $actor): array
    {
        if ($recommendations === []) {
            return [];
        }

        $shopRepairOrderIds = array_map(
            fn (TodayRecommendation $recommendation): int => $recommendation->repairOrderId,
            $recommendations,
        );

        $repairOrders = RepairOrder::query()
            ->whereIn('repair_order_id', $shopRepairOrderIds)
            ->get()
            ->keyBy('repair_order_id');

        $lifecycle = app(RepairOrderLifecycleTransition::class);

        return array_map(
            function (TodayRecommendation $recommendation) use ($repairOrders, $lifecycle, $actor): TodayRecommendation {
                $repairOrder = $repairOrders->get($recommendation->repairOrderId);

                if ($repairOrder === null) {
                    return $recommendation;
                }

                $canCloseLost = $lifecycle->blockingReason(
                    $repairOrder,
                    RepairOrderStatus::Closed->value,
                    $actor,
                    'lost',
                ) === null;

                return $recommendation->withCanCloseLost($canCloseLost);
            },
            $recommendations,
        );
    }
}
