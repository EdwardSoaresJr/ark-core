<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Documents\EstimateDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RepairOrderMileageUpdateController
{
    use RecordsRepairOrderEstimateMutation;
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcurrency $concurrency,
        EstimateDocumentService $documents,
    ): RedirectResponse|JsonResponse {
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'mileage_in' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'mileage_out' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);

        $mileageIn = filled($data['mileage_in'] ?? null) ? (int) $data['mileage_in'] : null;
        $mileageOut = filled($data['mileage_out'] ?? null) ? (int) $data['mileage_out'] : null;

        if ($mileageIn !== null && $mileageOut !== null && $mileageOut < $mileageIn) {
            throw ValidationException::withMessages([
                'mileage_out' => 'Mileage out must be greater than or equal to mileage in.',
            ]);
        }

        if ($repairOrder->mileage_in === $mileageIn && $repairOrder->mileage_out === $mileageOut) {
            return $this->respond($request, $repairOrder, $concurrency, 'Mileage unchanged.');
        }

        $repairOrder->forceFill([
            'mileage_in' => $mileageIn,
            'mileage_out' => $mileageOut,
        ])->save();

        $documents->markDirtyForRepairOrder($repairOrder);

        if (! $repairOrder->isTerminal()) {
            $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());
        }

        return $this->respond($request, $repairOrder->refresh(), $concurrency, 'Mileage updated.');
    }

    private function respond(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcurrency $concurrency,
        string $status,
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'mileage_in' => $repairOrder->mileage_in,
                'mileage_out' => $repairOrder->mileage_out,
                'estimate_version' => $concurrency->openedVersion($repairOrder),
            ]);
        }

        return redirect()
            ->back()
            ->with('status', $status);
    }
}
