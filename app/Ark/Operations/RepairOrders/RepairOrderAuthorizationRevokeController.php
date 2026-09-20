<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\RevokeCustomerAuthorizationAction;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderAuthorizationRevokeController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        ApprovalEvent $approvalEvent,
        RevokeCustomerAuthorizationAction $revokeAuthorization,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        abort_unless($approvalEvent->visit_id === $repairOrder->id, 404);

        $repairOrder->loadMissing(['concerns', 'customer']);

        $data = $request->validate([
            'source' => ['required', Rule::enum(ApprovalSource::class)],
            'revoked_by' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'concern_ids' => ['required', 'array', 'min:1'],
            'concern_ids.*' => ['integer', 'min:1'],
            'revert_disposition' => ['required', Rule::enum(RepairOrderConcernDisposition::class)],
        ]);

        $revertDisposition = RepairOrderConcernDisposition::from($data['revert_disposition']);

        if (! in_array($revertDisposition, [RepairOrderConcernDisposition::Recommended, RepairOrderConcernDisposition::Deferred], true)) {
            return back()
                ->withErrors(['revert_disposition' => 'Revoked scopes must return to recommended or deferred.'])
                ->withInput();
        }

        $revokeAuthorization->execute(
            repairOrder: $repairOrder,
            approvalEvent: $approvalEvent,
            source: ApprovalSource::from($data['source']),
            revokedBy: $data['revoked_by'],
            notes: $data['notes'] ?? null,
            concernIds: array_map('intval', $data['concern_ids']),
            revertDisposition: $revertDisposition,
            actor: $request->user(),
        );

        $totalsCalculator->recalculateRepairOrder($repairOrder->fresh());
        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('authorization-rail')
            ->with('status', 'Customer authorization revoked.');
    }
}
