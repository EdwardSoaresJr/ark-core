<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RepairOrderVehicleUpdateController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse|JsonResponse {
        $concurrency->guard($request, $repairOrder);
        $repairOrder->ensureOpenForEditing();

        if (! $repairOrder->canChangeVehicle()) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Vehicle can only be changed before scopes or line items are added.',
            ]);
        }

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer'],
        ]);

        $vehicle = Vehicle::query()
            ->whereKey($data['vehicle_id'])
            ->where('customer_id', $repairOrder->customer_id)
            ->first();

        if ($vehicle === null) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Choose a vehicle that belongs to this customer.',
            ]);
        }

        if ((int) $repairOrder->vehicle_id === (int) $vehicle->id) {
            return $this->respond($request, $repairOrder, $concurrency, 'Vehicle unchanged.');
        }

        $repairOrder->forceFill([
            'vehicle_id' => $vehicle->id,
        ])->save();

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return $this->respond(
            $request,
            $repairOrder->refresh()->load(['customer', 'vehicle']),
            $concurrency,
            'Vehicle updated.',
            $vehicle,
        );
    }

    private function respond(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcurrency $concurrency,
        string $status,
        ?Vehicle $vehicle = null,
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return RepairOrderIdentityJsonResponse::forVehicleReassignment(
                $repairOrder,
                $vehicle ?? $repairOrder->vehicle,
                $status,
                $concurrency->openedVersion($repairOrder),
            );
        }

        return redirect()
            ->back()
            ->with('status', $status);
    }
}
