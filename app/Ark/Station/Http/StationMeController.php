<?php

namespace App\Ark\Station\Http;

use App\Ark\Station\StationDeviceToken;
use App\Ark\Station\StationGlassConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StationMeController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var StationDeviceToken $token */
        $token = $request->attributes->get(AuthenticateStationDevice::REQUEST_ATTR);

        return response()->json([
            'surface' => 'advisor_station',
            'shop_identity' => config('shop.identity'),
            'station' => [
                'name' => $token->name,
                'paired' => true,
            ],
            'glass' => app(StationGlassConfig::class)->forToken($token),
        ]);
    }
}
