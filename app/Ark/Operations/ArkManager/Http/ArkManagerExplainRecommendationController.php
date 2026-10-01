<?php

namespace App\Ark\Operations\ArkManager\Http;

use App\Ark\Operations\ArkManager\ArkManagerService;
use App\Ark\Operations\Flow\OperationalFlowProjectionBuilder;
use App\Ark\Operations\Today\AdvisorTodayProjection;
use App\Ark\Operations\Today\AdvisorTodayRecommendationEngine;
use App\Ark\Operations\Today\AdvisorTodayShopRadarBuilder;
use App\Ark\Operations\Today\TodayCommitmentsProjection;
use App\Ark\Operations\Today\TodayPipelineProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ArkManagerExplainRecommendationController
{
    public function __invoke(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        AdvisorTodayRecommendationEngine $recommendationEngine,
        AdvisorTodayShopRadarBuilder $shopRadarBuilder,
        TodayPipelineProjection $pipelineProjection,
        TodayCommitmentsProjection $commitmentsProjection,
        OperationalFlowProjectionBuilder $flowProjectionBuilder,
        ArkManagerService $arkManager,
    ): JsonResponse {
        $validated = $request->validate([
            'repair_order_id' => ['required', 'integer', 'min:1'],
        ]);

        $today = AdvisorTodayProjection::resolve(
            $repairOrderQuery,
            $recommendationEngine,
            $shopRadarBuilder,
            $pipelineProjection,
            $commitmentsProjection,
            $flowProjectionBuilder,
            $request->user(),
        );

        $recommendation = collect($today->recommendations)
            ->first(fn ($row) => $row->repairOrderId === (int) $validated['repair_order_id']);

        if ($recommendation === null) {
            return response()->json(['message' => 'Recommendation not found for this repair order.'], 404);
        }

        $explanation = $arkManager->explainRecommendation($today, [
            'rule_key' => $recommendation->ruleKey,
            'title' => $recommendation->title,
            'why_reasons' => $recommendation->whyReasons,
            'impact_kind' => $recommendation->impactKind->value,
            'impact_label' => $recommendation->impactLabel,
            'suggested_action' => $recommendation->suggestedAction,
            'repair_order_id' => $recommendation->repairOrderId,
            'customer_name' => $recommendation->customerName,
        ]);

        return response()->json([
            'explanation' => $explanation->explanation,
            'source' => $explanation->source,
            'ai_enhanced' => $explanation->aiEnhanced,
        ]);
    }
}
