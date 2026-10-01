<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\RepairOrders\RepairOrderIdentityJsonResponse;
use App\Ark\Vehicles\Providers\ManualEntryProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehicleUpdateController
{
    public function __invoke(Request $request, Customer $customer, Vehicle $vehicle, EstimateDocumentService $documents, ManualEntryProvider $manualEntry): RedirectResponse|JsonResponse
    {
        abort_unless($vehicle->customer_id === $customer->id, 404);

        VehicleIdentityInput::mergeCoercedVinFromRequest($request);

        $data = $request->validate([
            'vin' => ['nullable', 'string', 'max:17'],
            'plate' => ['nullable', 'string', 'max:32'],
            'plate_state' => ['nullable', 'string', 'max:32'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'trim' => ['nullable', 'string', 'max:255'],
            'engine' => ['nullable', 'string', 'max:255'],
            'transmission' => ['nullable', 'string', 'max:255'],
            'drive' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'public_notes' => ['nullable', 'string', 'max:255'],
            'private_notes' => ['nullable', 'string', 'max:255'],
            'repair_order_id' => ['nullable', 'integer'],
        ]);

        unset($data['repair_order_id']);

        $data = [
            ...$data,
            ...array_filter(
                $manualEntry->normalize($data)->toPersistenceArray(),
                fn ($value): bool => $value !== null,
            ),
        ];

        $vehicle->update($data);
        $vehicle->refresh();
        $vehicle->repairOrders()->each(
            fn ($repairOrder) => $documents->markDirtyForRepairOrder($repairOrder),
        );

        if ($json = RepairOrderIdentityJsonResponse::forVehicleUpdate($request, $customer, $vehicle, 'Vehicle updated.')) {
            return $json;
        }

        $repairOrder = RepairOrderIdentityJsonResponse::resolveRepairOrderForRedirect(
            $request->integer('repair_order_id'),
        );

        if ($repairOrder !== null) {
            return redirect()
                ->route('operations.repair-orders.show', $repairOrder)
                ->with('status', 'Vehicle updated.');
        }

        return redirect()
            ->route('operations.customers.show', $customer)
            ->with('status', 'Vehicle updated.');
    }
}
