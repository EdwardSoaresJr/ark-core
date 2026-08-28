<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;

final class MobileIntakeVehicleProjection
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'label' => $vehicle->display_name,
            'vin' => $vehicle->authoritativeVin(),
            'plate' => $vehicle->plate,
            'plate_state' => $vehicle->plate_state,
            'year' => $vehicle->year,
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'trim' => $vehicle->trim,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupMatch(Vehicle $vehicle): array
    {
        $customer = $vehicle->customer;

        return [
            'match' => [
                'customer' => [
                    'id' => $customer?->id,
                    'name' => $customer?->name,
                    'phone' => $customer?->phone,
                    'display_phone' => $customer?->display_phone,
                ],
                'vehicle' => self::summary($vehicle),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function created(Vehicle $vehicle): array
    {
        return [
            'vehicle' => self::summary($vehicle),
        ];
    }
}
