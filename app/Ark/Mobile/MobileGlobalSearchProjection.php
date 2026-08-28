<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Customers\CustomerSearchQuery;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Vehicles\VehicleSearchQuery;
use App\Models\User;

/**
 * Universal shop search for mobile — customers, vehicles, RO number hints.
 */
final class MobileGlobalSearchProjection
{
    public function __construct(
        private readonly MobileIntakeCustomerProjection $customers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forQuery(string $query, User $viewer): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'query' => '',
                'repair_order_number' => null,
                'repair_orders' => [],
                'customers' => [],
                'vehicles' => [],
                'count' => 0,
            ];
        }

        $customerRows = CustomerSearchQuery::matching($query, 10)
            ->map(fn ($customer): array => $this->customers->searchResult($customer))
            ->values()
            ->all();

        $vehicleRows = VehicleSearchQuery::matching($query, 10)
            ->map(fn (Vehicle $vehicle): array => $this->vehicleResult($vehicle))
            ->values()
            ->all();

        $repairOrderNumber = $this->repairOrderNumberHint($query);
        $repairOrders = $this->repairOrdersMatching($query);

        return [
            'query' => $query,
            'repair_order_number' => $repairOrderNumber,
            'repair_orders' => $repairOrders,
            'customers' => $customerRows,
            'vehicles' => $vehicleRows,
            'count' => count($customerRows) + count($vehicleRows) + count($repairOrders)
                + ($repairOrderNumber !== null ? 1 : 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repairOrdersMatching(string $query): array
    {
        $like = '%'.$query.'%';

        return RepairOrder::query()
            ->with(['customer:id,first_name,last_name', 'vehicle:id,year,make,model'])
            ->where(function ($builder) use ($like): void {
                $builder
                    ->where('concern_summary', 'like', $like)
                    ->orWhereHas('concerns', fn ($concerns) => $concerns->where('summary', 'like', $like));
            })
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (RepairOrder $repairOrder): array => [
                'repair_order_id' => (int) $repairOrder->repair_order_id,
                'number_label' => '#'.$repairOrder->repair_order_id,
                'status_label' => $repairOrder->statusDisplayLabel(),
                'concern_summary' => $repairOrder->concern_summary,
                'customer_name' => $repairOrder->customer?->name,
                'vehicle_label' => $repairOrder->vehicle?->display_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function vehicleResult(Vehicle $vehicle): array
    {
        $customer = $vehicle->customer;
        $openRo = $vehicle->repairOrders->first();

        return [
            'id' => $vehicle->id,
            'label' => $vehicle->display_name,
            'vin' => $vehicle->vin,
            'plate' => $vehicle->plate,
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name,
            'open_repair_order_id' => $openRo?->repair_order_id,
            'open_repair_order_status_label' => $openRo?->status->label(),
        ];
    }

    private function repairOrderNumberHint(string $query): ?int
    {
        $trimmed = trim($query);

        if (preg_match('/^#?(\d+)$/', $trimmed, $matches) !== 1) {
            return null;
        }

        $number = (int) $matches[1];

        if ($number <= 0) {
            return null;
        }

        return RepairOrder::query()->where('repair_order_id', $number)->exists()
            ? $number
            : null;
    }
}
