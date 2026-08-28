<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Dragon\Assist\DragonAssistProjection;
use App\Ark\Dragon\HistoricalRecall\RequestHistoricalWorkRecallAssistAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST — create durable Historical Work Recall Assist after deterministic GET recall.
 * Deterministic GET remains zero-write.
 */
final class HistoricalWorkRecallAssistController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        WorkTemplate $workTemplate,
        ResolveHistoricalWorkRecall $resolve,
        RequestHistoricalWorkRecallAssistAction $requestAssist,
    ): JsonResponse {
        abort_unless(! $workTemplate->isRetired(), 404);

        $projection = $resolve->for($repairOrder, $workTemplate);
        $assist = $requestAssist->execute(
            $repairOrder,
            $workTemplate,
            $projection,
            $request->user(),
        );

        return response()->json([
            'template_id' => $workTemplate->id,
            'recall' => $projection->toArray(),
            'assist' => $assist !== null
                ? DragonAssistProjection::fromRequest($assist)
                : null,
        ]);
    }
}
