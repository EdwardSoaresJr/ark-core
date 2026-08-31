<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Vehicles\VehicleIdentityInput;
use App\Ark\Vehicles\VehicleIntelligenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleVinDecodeController
{
    public function __invoke(Request $request, VehicleIntelligenceManager $manager, OperationalEventRecorder $events): JsonResponse
    {
        VehicleIdentityInput::mergeCoercedVinFromRequest($request);

        $validator = Validator::make($request->all(), [
            'vin' => ['nullable', 'string', 'min:11', 'max:17'],
            'plate' => ['nullable', 'string', 'max:32'],
            'plate_state' => ['nullable', 'string', 'max:32'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $hasVin = strlen(trim((string) $request->input('vin', ''))) >= 11;
            $hasPlate = filled($request->input('plate')) && filled($request->input('plate_state'));

            if (! $hasVin && ! $hasPlate) {
                $validator->errors()->add('decode', 'Enter a VIN or plate and state to decode.');
            }

            if ($hasVin && $hasPlate) {
                $validator->errors()->add('decode', 'Decode using either VIN or plate — not both at once.');
            }
        });

        $data = $validator->validate();

        $plate = strtoupper(trim((string) ($data['plate'] ?? '')));
        $plateState = strtoupper(trim((string) ($data['plate_state'] ?? '')));
        $vin = trim((string) ($data['vin'] ?? ''));

        if ($vin !== '') {
            $decoded = $manager->decodeVin($vin);
            $failureMessage = 'Vehicle could not be decoded from that VIN.';
            $aggregateSeed = $vin;
        } else {
            $decoded = $manager->decodePlate($plate, $plateState);
            $failureMessage = 'Vehicle could not be decoded from that plate. Plate decode is not available.';
            $aggregateSeed = $plate.'|'.$plateState;
        }

        if ($decoded === null) {
            return response()->json([
                'message' => $failureMessage,
            ], 422);
        }

        $events->record(
            OperationalEventName::VehicleDecoded,
            'vehicle_identity',
            aggregateId: crc32($decoded->normalizedVehicleKey ?? $decoded->normalizedVin ?? $aggregateSeed),
            actor: $request->user(),
            payload: array_filter([
                'normalized_vin' => $decoded->normalizedVin,
                'normalized_vehicle_key' => $decoded->normalizedVehicleKey,
                'source' => $decoded->source,
                'plate' => $plate !== '' ? $plate : null,
                'plate_state' => $plateState !== '' ? $plateState : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        );

        $payload = $decoded->toFormArray();

        if ($plate !== '') {
            $payload['plate'] = $plate;
        }

        if ($plateState !== '') {
            $payload['plate_state'] = $plateState;
        }

        return response()->json($payload);
    }
}
