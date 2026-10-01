<?php

namespace App\Ark\Station\Http;

use App\Ark\Station\StationDeviceToken;
use App\Ark\Station\StationGlassConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StationGlassSettingsController
{
    public function __invoke(Request $request, StationGlassConfig $config): JsonResponse
    {
        /** @var StationDeviceToken $token */
        $token = $request->attributes->get(AuthenticateStationDevice::REQUEST_ATTR);

        $data = $request->validate([
            'appearance' => ['sometimes', 'in:light,dark,system'],
            'advisor_mode' => ['sometimes', 'in:one,two'],
            'primary_advisor_user_id' => ['sometimes', 'nullable', 'integer'],
            'secondary_advisor_user_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        return response()->json([
            'glass' => $config->updateToken($token, $data),
        ]);
    }
}
