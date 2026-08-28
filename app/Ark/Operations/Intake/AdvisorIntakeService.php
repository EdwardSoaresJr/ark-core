<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\AssignRepairOrderTechnician;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvisorIntakeService
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
        private readonly AssignRepairOrderTechnician $technicianAssignment,
    ) {}

    /**
     * @param  array{
     *     customer_id: int,
     *     vehicle_id: int,
     *     visit_reason?: string|null,
     *     advisor_notes?: string|null,
     *     assigned_technician_id?: int|null,
     *     mileage_in?: int|null,
     *     waiting_here?: bool,
     *     drop_off?: bool,
     *     needs_shuttle?: bool,
     *     fleet?: bool,
     *     warranty?: bool,
     *     billing_class?: string|null,
     *     tow_incoming?: bool,
     *     appointment?: bool,
     * }  $data
     */
    public function create(array $data, ?User $actor): RepairOrder
    {
        $customer = Customer::query()->findOrFail($data['customer_id']);

        if (filled($data['billing_class'] ?? null) && strcasecmp((string) $customer->customer_type, (string) $data['billing_class']) !== 0) {
            $customer->update(['customer_type' => $data['billing_class']]);
            $customer->refresh();
        }

        $vehicle = Vehicle::query()
            ->whereKey($data['vehicle_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if ($vehicle === null) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Choose a vehicle that belongs to this customer.',
            ]);
        }

        $visitReason = trim((string) ($data['visit_reason'] ?? ''));
        $assignedTechnicianId = filled($data['assigned_technician_id'] ?? null)
            ? (int) $data['assigned_technician_id']
            : null;

        if ($assignedTechnicianId !== null && $actor === null) {
            throw ValidationException::withMessages([
                'assigned_technician_id' => 'Technician assignment requires an authenticated user.',
            ]);
        }

        return DB::transaction(function () use ($data, $customer, $vehicle, $visitReason, $assignedTechnicianId, $actor): RepairOrder {
            $repairOrder = RepairOrder::query()->create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'status' => RepairOrderStatus::Draft,
                'concern_summary' => '',
                'visit_reason' => $visitReason !== '' ? $visitReason : null,
                'opened_at' => now(),
                'mileage_in' => filled($data['mileage_in'] ?? null) ? (int) $data['mileage_in'] : null,
                'tow_incoming' => (bool) ($data['tow_incoming'] ?? false),
                'waiting_here' => (bool) ($data['waiting_here'] ?? false),
                'drop_off' => (bool) ($data['drop_off'] ?? false),
                'needs_shuttle' => (bool) ($data['needs_shuttle'] ?? false),
                'warranty' => (bool) ($data['warranty'] ?? false),
                'fleet' => (bool) ($data['fleet'] ?? false),
                'appointment' => (bool) ($data['appointment'] ?? false),
            ]);

            if ($assignedTechnicianId !== null && $actor !== null) {
                $this->technicianAssignment->assign($repairOrder, $assignedTechnicianId, $actor);
            } else {
                // Solo / mobile-solo: the only staff member is the technician.
                $this->technicianAssignment->assignSoleStaffUserIfApplicable($repairOrder, $actor);
            }

            $this->events->record(
                OperationalEventName::RepairOrderCreated,
                $repairOrder,
                actor: $actor,
                payload: [
                    'intake' => 'advisor',
                    'has_visit_reason' => $visitReason !== '',
                ],
            );

            return $repairOrder->fresh(['customer', 'vehicle', 'assignedTechnician', 'concerns']);
        });
    }
}
