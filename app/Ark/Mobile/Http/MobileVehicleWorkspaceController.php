<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Mobile\MobileVehicleWorkspaceProjection;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileVehicleWorkspaceController
{
    public function __invoke(
        Request $request,
        Vehicle $vehicle,
        MobileStaffAccess $access,
        MobileVehicleWorkspaceProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewVehicle($request->user(), $vehicle), 403);

        return response()->json($projection->forVehicle($vehicle, $request->user()));
    }
}
