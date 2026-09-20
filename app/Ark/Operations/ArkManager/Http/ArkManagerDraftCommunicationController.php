<?php

namespace App\Ark\Operations\ArkManager\Http;

use App\Ark\Operations\ArkManager\ArkManagerService;
use App\Ark\Operations\Flow\OperationalFlowProjectionBuilder;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Today\AdvisorTodayProjection;
use App\Ark\Operations\Today\AdvisorTodayRecommendationEngine;
use App\Ark\Operations\Today\AdvisorTodayShopRadarBuilder;
use App\Ark\Operations\Today\TodayCommitmentsProjection;
use App\Ark\Operations\Today\TodayPipelineProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ArkManagerDraftCommunicationController
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
            'channel' => ['required', 'string', 'in:sms,email'],
            'customer_name' => ['required', 'string', 'max:120'],
            'repair_order_id' => ['nullable', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'in:approval_follow_up,pickup_follow_up,general_follow_up'],
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

        $draftContext = [
            'channel' => $validated['channel'],
            'customer_name' => $validated['customer_name'],
            'repair_order_id' => $validated['repair_order_id'] ?? null,
            'purpose' => $validated['purpose'] ?? 'general_follow_up',
            'shop_name' => ShopSettings::current()->shop_name ?: 'the shop',
        ];

        $draft = $arkManager->draftCommunication($today, $draftContext);

        return response()->json([
            'channel' => $draft->channel,
            'subject' => $draft->subject,
            'body' => $draft->body,
            'source' => $draft->source,
            'ai_enhanced' => $draft->aiEnhanced,
            'requires_human_approval' => true,
        ]);
    }
}
