<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileIntakeVehicleProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\VehicleIdentityInput;
use App\Ark\Vehicles\Providers\ManualEntryProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileIntakeVehicleStoreController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        MobileStaffAccess $access,
        MobileIntakeVehicleProjection $projection,
        ManualEntryProvider $manualEntry,
    ): JsonResponse {
        abort_unless($access->canManageVehicles($request->user()), 403);

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
            'fuel_type' => ['nullable', 'string', 'max:255'],
            'body_style' => ['nullable', 'string', 'max:255'],
        ]);

        VehicleIdentityInput::validate($data);

        $data = [
            ...$data,
            ...array_filter(
                $manualEntry->normalize($data)->toPersistenceArray(),
                fn ($value): bool => $value !== null,
            ),
        ];

        $vehicle = $customer->vehicles()->create($data);

        return response()->json($projection->created($vehicle), 201);
    }
}
