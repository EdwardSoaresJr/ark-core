<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSearchQuery;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
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
        $mode = (string) $request->query('mode', '');
        $showUnlinkedForm = $customer === null && (
            $mode === 'new'
            || $context->hasBookingContact()
            || $context->leadId !== null
            || ($context->conversationId !== null && ! $context->needsCustomerIdentification)
        );

        $defaultConcern = old('concern');
        if ($defaultConcern === null) {
            $defaultConcern = $context->defaultConcern;
        }

        $preferenceLead = $this->resolvePreferenceLead($request, $context);
        $preferredPeriod = old('preferred_period', $preferenceLead?->preferredPeriod());
        $preferredDate = old(
            'preferred_date',
            $request->filled('preferred_date')
                ? (string) $request->string('preferred_date')
                : ($request->filled('starts_at')
                    ? ShopDisplayTimezone::parseLocal((string) $request->string('starts_at'))?->format('Y-m-d')
                    : null),
        );

        return view('operations.appointments.create', [
            'customer' => $customer,
            'repairOrder' => $repairOrder,
            'scheduleContext' => $context,
            'showUnlinkedForm' => $showUnlinkedForm,
            'searchQuery' => $searchQuery,
            'searchCustomers' => $customer === null && $searchQuery !== '' && ! $showUnlinkedForm
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
            'preferredPeriod' => is_string($preferredPeriod) ? $preferredPeriod : null,
            'preferredDate' => is_string($preferredDate) ? $preferredDate : null,
            'requestPreferenceDetail' => is_string($preferredPeriod) && $preferredPeriod !== ''
                ? AppointmentExpectationFormatter::requestedDetail(
                    is_string($preferredDate) ? $preferredDate : null,
                    $preferredPeriod,
                )
                : null,
            'defaultConcern' => $defaultConcern,
            'defaultContactName' => old('contact_name', $context->contactName),
            'defaultContactPhone' => old('contact_phone', $context->contactPhone),
            'defaultContactEmail' => old('contact_email', $context->contactEmail),
            'vehicleContextLabel' => $context->vehicleContextLabel,
            'defaultLeadId' => $context->leadId,
            'slotMinutes' => AppointmentSlotMinutes::resolve(),
            'scheduleSearchPreserve' => array_filter([
                'conversation' => $context->conversationId,
                'lead' => $context->leadId ?? ($request->integer('lead') ?: null),
                'preferred_period' => is_string($preferredPeriod) ? $preferredPeriod : null,
                'preferred_date' => is_string($preferredDate) ? $preferredDate : null,
                'starts_at' => $request->filled('starts_at') ? (string) $request->string('starts_at') : null,
                'ends_at' => $request->filled('ends_at') ? (string) $request->string('ends_at') : null,
                'duration_minutes' => $request->integer('duration_minutes') ?: null,
                'technician_user_id' => $request->integer('technician_user_id') ?: null,
                'workstation_id' => $request->integer('workstation_id') ?: null,
                'vehicle' => $context->vehicleId,
            ]),
        ]);
    }

    private function resolvePreferenceLead(Request $request, ScheduleContext $context): ?Lead
    {
        $leadId = $context->leadId ?? ($request->integer('lead') ?: null);
        if ($leadId) {
            return Lead::query()->find($leadId);
        }

        if ($context->conversationId !== null) {
            return Lead::query()
                ->where('conversation_id', $context->conversationId)
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }
}
