<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class VehicleShowController
{
    public function __invoke(Request $request, Vehicle $vehicle): View
    {
        $vehicle->loadMissing('customer');

        $activeStatuses = RepairOrderStatus::operationalQueueValues();

        // Active work is queried independently so open ROs never vanish behind a history limit.
        $activeRepairOrders = RepairOrder::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', $activeStatuses)
            ->with(['customer', 'vehicle', 'lines.concern'])
            ->latest()
            ->get();

        $activeIds = $activeRepairOrders->pluck('id')->all();

        $recentRepairOrders = RepairOrder::query()
            ->where('vehicle_id', $vehicle->id)
            ->when($activeIds !== [], fn ($query) => $query->whereNotIn('id', $activeIds))
            ->with(['customer', 'vehicle', 'lines.concern'])
            ->latest()
            ->limit(8)
            ->get();

        return view('operations.vehicles.show', [
            'vehicle' => $vehicle,
            'customer' => $vehicle->customer,
            'activeRepairOrders' => $activeRepairOrders,
            'recentRepairOrders' => $recentRepairOrders,
        ]);
    }
}
