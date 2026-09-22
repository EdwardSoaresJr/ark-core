<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DismissEstimateCompanionSuggestionController
{
    public function __invoke(Request $request, RepairOrder $repairOrder): RedirectResponse|JsonResponse
    {
        $repairOrder->forceFill([
            'companion_suggestion_dismissed_hash' => EstimateCompanionSuggestionFingerprint::for($repairOrder),
        ])->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Suggestion dismissed.',
            ]);
        }

        return redirect()->back()->with('status', 'Suggestion dismissed.');
    }
}
