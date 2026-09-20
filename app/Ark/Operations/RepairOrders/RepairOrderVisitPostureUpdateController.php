<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderVisitPostureUpdateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse|JsonResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'visit_mode' => ['required', Rule::enum(RepairOrderVisitMode::class)],
        ]);

        $visitMode = RepairOrderVisitMode::from($data['visit_mode']);
        $currentMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);

        if ($currentMode === $visitMode) {
            return $this->respond($request, $visitMode, 'Visit posture unchanged.');
        }

        $visitMode->applyTo($repairOrder);
        $repairOrder->save();

        return $this->respond($request, $visitMode, 'Visit posture updated.');
    }

    private function respond(
        Request $request,
        RepairOrderVisitMode $visitMode,
        string $status,
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'visit_mode' => $visitMode->value,
                'visit_mode_label' => $visitMode->label(),
            ]);
        }

        return redirect()
            ->back()
            ->with('status', $status);
    }
}
