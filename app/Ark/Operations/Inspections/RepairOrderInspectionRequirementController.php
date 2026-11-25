<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderInspectionRequirementController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        SetRepairOrderInspectionRequirementAction $action,
    ): RedirectResponse {
        abort_unless(SetRepairOrderInspectionRequirementAction::canManage($request->user()), 403);

        $data = $request->validate([
            'inspection_lifecycle_policy' => ['nullable', 'string', Rule::in([
                '',
                InspectionRequirement::Optional->value,
                InspectionRequirement::Recommended->value,
                InspectionRequirement::Required->value,
            ])],
        ]);

        $raw = $data['inspection_lifecycle_policy'] ?? '';
        $requirement = $raw === '' || $raw === null
            ? null
            : InspectionRequirement::from($raw);

        $action->execute($repairOrder, $requirement, $request->user());

        $label = $requirement?->label() ?? 'Shop default';

        return redirect()
            ->to(InspectionWorkspaceUrl::advisorReview($repairOrder))
            ->with('status', 'Inspection requirement updated: '.$label.'.');
    }
}
