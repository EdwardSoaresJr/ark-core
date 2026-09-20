<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileIntakeVehicleProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Intake\VehicleIntakeLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileIntakeVehicleLookupController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileIntakeVehicleProjection $projection,
    ): JsonResponse {
        abort_unless($access->canPerformIntake($request->user()), 403);

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

        return response()->json($projection->lookupMatch($vehicle));
    }
}
