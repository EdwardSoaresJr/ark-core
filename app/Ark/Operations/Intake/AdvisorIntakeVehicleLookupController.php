<?php

namespace App\Ark\Operations\Intake;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvisorIntakeVehicleLookupController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json([
                'message' => 'Enter a VIN or license plate.',
            ], 422);
        }

        $vehicle = VehicleIntakeLookup::resolve($query);

        if ($vehicle === null || $vehicle->customer === null) {
            return response()->json([
                'message' => VehicleIntakeLookup::notFoundMessage($query),
            ], 404);
        }

        return response()->json([
            'customer_id' => $vehicle->customer_id,
            'vehicle_id' => $vehicle->id,
            'customer_name' => $vehicle->customer->name,
            'vehicle_name' => $vehicle->display_name,
            'vin' => $vehicle->vin,
            'plate' => $vehicle->plate,
        ]);
    }
}
