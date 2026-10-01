<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RepairOrderIdentityJsonResponse
{
    public static function forCustomerUpdate(
        Request $request,
        Customer $customer,
        string $status,
    ): ?JsonResponse {
        if (! self::wantsIdentityJson($request)) {
            return null;
        }

        $repairOrderId = $request->integer('repair_order_id');

        if ($repairOrderId === 0) {
            return response()->json([
                'status' => $status,
                'customer' => OperationalIdentityPresenter::customerIdentity($customer),
            ]);
        }

        $repairOrder = self::resolveRepairOrder($repairOrderId);

        abort_unless($repairOrder->customer_id === $customer->id, 403);

        return response()->json(self::payload($repairOrder, $status, customer: $customer));
    }

    public static function forVehicleUpdate(
        Request $request,
        Customer $customer,
        Vehicle $vehicle,
        string $status,
    ): ?JsonResponse {
        if (! self::wantsIdentityJson($request)) {
            return null;
        }

        $repairOrderId = $request->integer('repair_order_id');

        if ($repairOrderId === 0) {
            return response()->json([
                'status' => $status,
                'vehicle' => OperationalIdentityPresenter::vehicleIdentity($vehicle),
            ]);
        }

        $repairOrder = self::resolveRepairOrder($repairOrderId);

        abort_unless(
            $repairOrder->customer_id === $customer->id && $repairOrder->vehicle_id === $vehicle->id,
            403,
        );

        return response()->json(self::payload($repairOrder, $status, vehicle: $vehicle));
    }

    public static function forVehicleReassignment(
        RepairOrder $repairOrder,
        Vehicle $vehicle,
        string $status,
        int $estimateVersion,
    ): JsonResponse {
        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator']);
        $repairOrder->setRelation('vehicle', $vehicle);

        $identity = OperationalIdentityPresenter::forRepairOrder($repairOrder);

        return response()->json([
            'status' => $status,
            'vehicle' => $identity['vehicle'],
            'estimate_version' => $estimateVersion,
            'reload' => true,
        ]);
    }

    /**
     * Workspace continuity posts with Accept: text/html + X-Requested-With.
     * Prefer JSON identity payloads for those AJAX saves (not only expectsJson).
     */
    public static function wantsIdentityJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

    public static function resolveRepairOrderForRedirect(int $repairOrderId): ?RepairOrder
    {
        if ($repairOrderId <= 0) {
            return null;
        }

        return self::resolveRepairOrder($repairOrderId);
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(
        RepairOrder $repairOrder,
        string $status,
        ?Customer $customer = null,
        ?Vehicle $vehicle = null,
    ): array {
        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator']);

        if ($customer !== null) {
            $repairOrder->setRelation('customer', $customer);
        }

        if ($vehicle !== null) {
            $repairOrder->setRelation('vehicle', $vehicle);
        }

        $identity = OperationalIdentityPresenter::forRepairOrder($repairOrder);

        return [
            'status' => $status,
            'customer' => $identity['customer'],
            'vehicle' => $identity['vehicle'],
            'estimate_version' => app(RepairOrderConcurrency::class)->openedVersion($repairOrder),
        ];
    }

    private static function resolveRepairOrder(int $repairOrderId): RepairOrder
    {
        return RepairOrder::query()
            ->with(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator'])
            ->where(function ($query) use ($repairOrderId): void {
                $query->whereKey($repairOrderId)
                    ->orWhere('repair_order_id', $repairOrderId);
            })
            ->firstOrFail();
    }
}
