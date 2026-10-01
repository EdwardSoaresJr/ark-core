<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AddDeferredConcernToEstimateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        AddDeferredConcernToEstimateAction $action,
    ): RedirectResponse {
        $result = $action->handle($concern, $repairOrder, $request->user());

        $message = $result['created']
            ? 'Added to this estimate: '.$result['concern']->summary
            : 'Already on this estimate.';

        return back()->with('status', $message);
    }
}
