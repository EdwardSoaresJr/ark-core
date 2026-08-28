<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Mobile\MobileVinDecodeProjection;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Vehicles\VehicleIntelligenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileVinDecodeController
{
    public function __invoke(
        Request $request,
        VehicleIntelligenceManager $manager,
        MobileVinDecodeProjection $projection,
        MobileStaffAccess $access,
        OperationalEventRecorder $events,
    ): JsonResponse {
        abort_unless($access->canDecodeVin($request->user()), 403);

        $data = $request->validate([
            'vin' => ['required', 'string', 'min:11', 'max:17'],
        ]);

        $decoded = $manager->decodeVin($data['vin']);

        if ($decoded === null || ! $decoded->isUsable()) {
            return response()->json([
                'message' => 'Vehicle could not be decoded from that VIN.',
            ], 422);
        }

        $events->record(
            OperationalEventName::VehicleDecoded,
            'vehicle_identity',
            aggregateId: crc32($decoded->normalizedVehicleKey ?? $decoded->normalizedVin ?? $data['vin']),
            actor: $request->user(),
            payload: [
                'normalized_vin' => $decoded->normalizedVin,
                'normalized_vehicle_key' => $decoded->normalizedVehicleKey,
                'source' => $decoded->source,
                'surface' => 'mobile',
            ],
        );

        return response()->json($projection->present($decoded));
    }
}
