<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;

final class PortalVehicleRecordsLink
{
    /**
     * @return array{url: string, label: string, authenticated: bool}
     */
    public function forVehicle(?Customer $viewer, Vehicle $vehicle): array
    {
        $vehiclePath = route('portal.vehicles.show', $vehicle, absolute: false);

        if ($viewer !== null && (int) $viewer->id === (int) $vehicle->customer_id) {
            return [
                'url' => $vehiclePath,
                'label' => 'See all records for this vehicle',
                'authenticated' => true,
            ];
        }

        return [
            'url' => route('portal.access', ['return' => $vehiclePath]),
            'label' => 'Access all records for this vehicle',
            'authenticated' => false,
        ];
    }
}
