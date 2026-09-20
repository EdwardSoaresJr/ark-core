<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Intake\IntakeWorkspaceSession;
use App\Ark\Vehicles\Providers\ManualEntryProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehicleStoreController
{
    public function __invoke(Request $request, Customer $customer, ManualEntryProvider $manualEntry): RedirectResponse
    {
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
            'intake' => ['nullable', 'boolean'],
        ]);

        VehicleIdentityInput::validate($data);

        $returnToIntake = $request->boolean('intake');
        unset($data['intake']);

        $data = [
            ...$data,
            ...array_filter(
                $manualEntry->normalize($data)->toPersistenceArray(),
                fn ($value): bool => $value !== null,
            ),
        ];

        $vehicle = $customer->vehicles()->create($data);

        if ($returnToIntake) {
            return redirect()
                ->to(IntakeWorkspaceSession::routeFromRequestOrInput($request, [
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                ]))
                ->with('status', 'Vehicle added · '.$vehicle->display_name);
        }

        return redirect()
            ->route('operations.customers.show', $customer)
            ->with('status', 'Vehicle added.');
    }
}
