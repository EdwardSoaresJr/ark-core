<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class VehicleShowController
{
    public function __invoke(Request $request, Vehicle $vehicle): View
    {
        $vehicle->load([
            'customer',
            'repairOrders' => fn ($repairOrders) => $repairOrders
                ->with(['customer', 'vehicle', 'lines.concern'])
                ->latest()
                ->limit(12),
        ]);

        $activeStatuses = RepairOrderStatus::operationalQueueValues();
        $activeRepairOrders = $vehicle->repairOrders->filter(
            fn ($repairOrder) => in_array($repairOrder->status->value, $activeStatuses, true),
        );
        $recentRepairOrders = $vehicle->repairOrders
            ->reject(fn ($repairOrder) => $activeRepairOrders->contains('id', $repairOrder->id))
            ->take(8)
            ->values();

        return view('operations.vehicles.show', [
            'vehicle' => $vehicle,
            'customer' => $vehicle->customer,
            'activeRepairOrders' => $activeRepairOrders->values(),
            'recentRepairOrders' => $recentRepairOrders,
        ]);
    }
}
