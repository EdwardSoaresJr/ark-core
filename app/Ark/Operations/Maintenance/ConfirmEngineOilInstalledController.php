<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ConfirmEngineOilInstalledController
{
    public function show(RepairOrder $repairOrder, MaintenanceService $maintenanceService): View
    {
        $this->assertBelongs($repairOrder, $maintenanceService);

        $prior = $maintenanceService->currentEvent
            ?? MaintenanceServiceEvent::latestCurrentForVehicle(
                (int) $maintenanceService->vehicle_id,
                MaintenanceServiceKind::EngineOil,
            );

        $mileage = $repairOrder->resolvedMileageOut()
            ?? $repairOrder->resolvedMileageIn()
            ?? $prior?->service_mileage;

        return view('operations.maintenance.confirm-engine-oil', [
            'repairOrder' => $repairOrder,
            'service' => $maintenanceService,
            'priorEvent' => $prior,
            'defaultMileage' => $mileage,
            'isCorrection' => $maintenanceService->current_event_id !== null,
        ]);
    }

    public function store(
        Request $request,
        RepairOrder $repairOrder,
        MaintenanceService $maintenanceService,
        ConfirmEngineOilInstalledAction $action,
    ): RedirectResponse {
        $this->assertBelongs($repairOrder, $maintenanceService);

        $data = $request->validate([
            'oil_brand' => ['required', 'string', 'max:120'],
            'viscosity' => ['required', 'string', 'max:32'],
            'quantity_qt' => ['required', 'numeric', 'min:0.1', 'max:99.99'],
            'filter_part' => ['required', 'string', 'max:120'],
            'washer' => ['required', Rule::in([
                MaintenanceWasherState::Installed->value,
                MaintenanceWasherState::NotRequired->value,
                MaintenanceWasherState::NotReplaced->value,
            ])],
            'service_mileage' => ['required', 'integer', 'min:1', 'max:9999999'],
            'reset_reminder' => ['nullable', 'boolean'],
        ]);

        if ($maintenanceService->current_event_id !== null) {
            $data['supersede_event_id'] = $maintenanceService->current_event_id;
        }

        $action->handle($maintenanceService, $request->user(), $data);

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Oil service installed and recorded.');
    }

    private function assertBelongs(RepairOrder $repairOrder, MaintenanceService $service): void
    {
        abort_unless(
            (int) $service->repair_order_id === (int) $repairOrder->id
            && $service->kind === MaintenanceServiceKind::EngineOil,
            404,
        );
    }
}
