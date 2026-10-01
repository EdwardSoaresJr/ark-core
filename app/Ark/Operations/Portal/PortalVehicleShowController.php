<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class PortalVehicleShowController
{
    public function __construct(
        private readonly CustomerVehicleDetailProjection $vehicleDetail,
        private readonly PortalObservationRecorder $observation,
    ) {}

    public function __invoke(Vehicle $vehicle): View
    {
        /** @var Customer $customer */
        $customer = Auth::guard('portal')->user();

        abort_unless($vehicle->customer_id === $customer->id, 404);

        $detail = $this->vehicleDetail->forVehicle($vehicle, $customer);

        $this->observation->vehicleViewed($customer, $vehicle, $detail);

        return view('portal.vehicles.show', [
            'customer' => $customer,
            'vehicle' => $vehicle,
            'detail' => $detail,
        ]);
    }
}
