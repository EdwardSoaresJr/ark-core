<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehicleDestroyController
{
    public function __invoke(Request $request, Customer $customer, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->customer_id === $customer->id, 404);

        abort_if(
            $vehicle->repairOrders()->exists(),
            422,
            'Remove repair orders before deleting this vehicle.',
        );

        $displayName = $vehicle->display_name;

        $vehicle->delete();

        return redirect()
            ->route('operations.customers.show', $customer)
            ->with('status', 'Vehicle removed · '.$displayName);
    }
}
