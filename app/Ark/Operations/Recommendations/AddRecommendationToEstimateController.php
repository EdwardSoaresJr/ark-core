<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AddRecommendationToEstimateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        Recommendation $recommendation,
        AddRecommendationToEstimateAction $action,
    ): RedirectResponse {
        abort_unless(
            (int) $recommendation->vehicle_id === (int) $repairOrder->vehicle_id
            && (int) $recommendation->customer_id === (int) $repairOrder->customer_id,
            404,
        );

        $result = $action->handle($recommendation, $repairOrder, $request->user());

        $message = $result['created']
            ? 'Added to this estimate: '.$recommendation->title
            : 'Already on this estimate.';

        return back()->with('status', $message);
    }
}
