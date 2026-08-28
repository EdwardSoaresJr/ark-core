<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Leads\LeadConverter;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvisorIntakeStoreController
{
    public function __construct(
        private readonly AdvisorIntakeService $intake,
        private readonly LeadConverter $leadConverter,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $billingClassNames = collect(ShopSettings::current()->customerTypeRows())
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'visit_reason' => ['nullable', 'string', 'max:5000'],
            'visit_mode' => ['required', Rule::in(['waiting_here', 'drop_off', 'needs_shuttle', 'tow_incoming'])],
            'billing_class' => ['nullable', Rule::in($billingClassNames)],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ]);

        $visitMode = $request->input('visit_mode');
        $billingClass = trim((string) $request->input('billing_class', ''));

        $data['waiting_here'] = $visitMode === 'waiting_here';
        $data['drop_off'] = $visitMode === 'drop_off';
        $data['needs_shuttle'] = $visitMode === 'needs_shuttle';
        $data['tow_incoming'] = $visitMode === 'tow_incoming';
        $data['billing_class'] = $billingClass !== '' ? $billingClass : null;
        $data['fleet'] = strcasecmp($billingClass, 'Fleet') === 0;
        $data['warranty'] = strcasecmp($billingClass, 'Warranty') === 0;

        $repairOrder = $this->intake->create($data, $request->user());

        $this->leadConverter->convertFromRepairOrder(
            $repairOrder,
            $request->integer('lead_id') ?: null,
            $request->user(),
        );

        $closeIntakeWorkspaceId = IntakeWorkspaceSession::idFromRequestOrInput($request);

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('visit-reason')
            ->with('status', 'RO #'.$repairOrder->repair_order_id.' opened — estimate is empty until you add work.')
            ->with('workspace_close_intake_ws', $closeIntakeWorkspaceId);
    }
}
