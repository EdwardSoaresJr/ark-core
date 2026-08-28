<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSearchQuery;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders the appointment create form from a resolved ScheduleContext.
 * Shared by /app/schedule and legacy /app/appointments/create.
 */
final class PresentAppointmentCreateForm
{
    public function __construct(
        private readonly AppointmentStaffOptions $staff,
    ) {}

    public function __invoke(Request $request, ScheduleContext $context): View
    {
        $repairOrder = $context->repairOrderId !== null
            ? RepairOrder::query()->with(['customer.vehicles', 'vehicle'])->find($context->repairOrderId)
            : null;

        $customer = $context->customerId !== null
            ? Customer::query()->with('vehicles')->findOrFail($context->customerId)
            : null;

        $searchQuery = trim((string) $request->query('q', ''));

        $defaultConcern = old('concern');
        if ($defaultConcern === null) {
            $defaultConcern = $context->defaultConcern;
        }

        return view('operations.appointments.create', [
            'customer' => $customer,
            'repairOrder' => $repairOrder,
            'scheduleContext' => $context,
            'searchQuery' => $searchQuery,
            'searchCustomers' => $customer === null && $searchQuery !== ''
                ? CustomerSearchQuery::matching($searchQuery)
                : collect(),
            'selectedVehicleId' => $context->vehicleId,
            'advisors' => $this->staff->advisors(),
            'technicians' => $this->staff->technicians(),
            'workstations' => $this->staff->schedulableWorkstations(),
            'defaultAdvisorId' => $request->user()?->id,
            'defaultStartsAt' => $request->filled('starts_at') ? (string) $request->string('starts_at') : null,
            'defaultEndsAt' => $request->filled('ends_at') ? (string) $request->string('ends_at') : null,
            'defaultDurationMinutes' => $request->integer('duration_minutes') ?: null,
            'defaultTechnicianId' => $request->integer('technician_user_id') ?: null,
            'defaultWorkstationId' => $request->integer('workstation_id') ?: null,
            'defaultConcern' => $defaultConcern,
            'slotMinutes' => AppointmentSlotMinutes::resolve(),
            'scheduleSearchPreserve' => array_filter([
                'conversation' => $context->conversationId,
                'starts_at' => $request->filled('starts_at') ? (string) $request->string('starts_at') : null,
                'ends_at' => $request->filled('ends_at') ? (string) $request->string('ends_at') : null,
                'duration_minutes' => $request->integer('duration_minutes') ?: null,
                'technician_user_id' => $request->integer('technician_user_id') ?: null,
                'workstation_id' => $request->integer('workstation_id') ?: null,
                'vehicle' => $context->vehicleId,
            ]),
        ]);
    }
}
