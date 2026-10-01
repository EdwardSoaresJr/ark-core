<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Leads\LeadConverter;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderDraftStoreController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        OperationalEventRecorder $events,
        LeadConverter $leadConverter,
        AssignRepairOrderTechnician $technicianAssignment,
    ): RedirectResponse {
        $data = $request->validate([
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where('customer_id', $customer->id),
            ],
            'visit_reason' => ['required', 'string', 'max:2000'],
        ]);

        $vehicle = Vehicle::query()
            ->whereKey($data['vehicle_id'])
            ->whereBelongsTo($customer)
            ->firstOrFail();

        $visitReason = trim($data['visit_reason']);

        $standardTemplate = DefaultInspectionTemplateCatalog::standardTemplate();

        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => RepairOrderStatus::Draft,
            'concern_summary' => '',
            'visit_reason' => $visitReason !== '' ? $visitReason : null,
            'required_inspection_template_id' => $standardTemplate?->id,
            'opened_at' => now(),
        ]);

        $events->record(
            OperationalEventName::RepairOrderCreated,
            $repairOrder,
            actor: $request->user(),
            payload: [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'status' => $repairOrder->status->value,
                'intake' => 'customer_hub',
                'has_visit_reason' => $visitReason !== '',
            ],
        );

        // Solo / mobile-solo: the only staff member is the technician.
        $technicianAssignment->assignSoleStaffUserIfApplicable($repairOrder, $request->user());

        $leadConverter->convertFromRepairOrder($repairOrder, null, $request->user());

        return redirect()->route('operations.repair-orders.show', $repairOrder);
    }
}
