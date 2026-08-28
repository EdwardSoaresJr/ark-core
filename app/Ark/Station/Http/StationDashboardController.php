<?php

namespace App\Ark\Station\Http;

use App\Ark\Station\StationDashboardProjection;
use App\Ark\Station\StationDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StationDashboardController
{
    public function __invoke(Request $request, StationDashboardProjection $station): JsonResponse
    {
        /** @var StationDeviceToken $token */
        $token = $request->attributes->get(AuthenticateStationDevice::REQUEST_ATTR);

        return response()->json($station->payload($token));
    }
}
