<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only Historical Work Recall for a Saved Work selection.
 * GET must not mutate anything.
 */
final class HistoricalWorkRecallController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        WorkTemplate $workTemplate,
        ResolveHistoricalWorkRecall $resolve,
    ): JsonResponse {
        abort_unless(! $workTemplate->isRetired(), 404);

        $projection = $resolve->for($repairOrder, $workTemplate);

        return response()->json([
            'template_id' => $workTemplate->id,
            'recall' => $projection->toArray(),
        ]);
    }
}
