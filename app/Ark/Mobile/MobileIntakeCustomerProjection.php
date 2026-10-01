<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;

final class MobileIntakeCustomerProjection
{
    /**
     * @return array<string, mixed>
     */
    public function searchResult(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'display_phone' => $customer->display_phone,
            'email' => $customer->email,
            'customer_type' => $customer->customer_type ?: 'Retail',
            'vehicles' => $customer->vehicles
                ->map(fn (Vehicle $vehicle): array => MobileIntakeVehicleProjection::summary($vehicle))
                ->values()
                ->all(),
            'open_repair_orders' => $customer->repairOrders
                ->map(fn (RepairOrder $repairOrder): array => [
                    'repair_order_id' => $repairOrder->repair_order_id,
                    'status' => $repairOrder->status->value,
                    'status_label' => $repairOrder->status->label(),
                    'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Customer $customer): array
    {
        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
                'display_phone' => $customer->display_phone,
                'email' => $customer->email,
                'customer_type' => $customer->customer_type ?: 'Retail',
                'vehicles' => $customer->vehicles
                    ->sortByDesc('id')
                    ->map(fn (Vehicle $vehicle): array => MobileIntakeVehicleProjection::summary($vehicle))
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function created(Customer $customer): array
    {
        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
                'display_phone' => $customer->display_phone,
                'email' => $customer->email,
                'customer_type' => $customer->customer_type ?: 'Retail',
                'vehicles' => [],
            ],
        ];
    }
}
