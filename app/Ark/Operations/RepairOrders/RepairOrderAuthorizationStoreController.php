<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\RecordCustomerAuthorizationAction;
use App\Ark\Operations\Approvals\ResolveStaffAuthorizationType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderAuthorizationStoreController
{
    use RecordsRepairOrderEstimateMutation;
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RecordCustomerAuthorizationAction $recordAuthorization,
        EstimateTotalsCalculator $totalsCalculator,
        ResolveStaffAuthorizationType $resolveAuthorizationType,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $repairOrder->loadMissing(['concerns', 'customer']);

        $data = $request->validate([
            'source' => ['required', Rule::enum(ApprovalSource::class)],
            'approved_by' => ['required', 'string', 'max:255'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $totalsCalculator->hasApprovedInvoiceableWork($repairOrder)) {
            return back()
                ->withErrors(['authorization' => 'Mark at least one scope as approved on the estimate before recording customer authorization.'])
                ->withInput();
        }

        $approvedAmountCents = isset($data['approved_amount'])
            ? (int) round(((float) $data['approved_amount']) * 100)
            : null;

        $approvalType = $resolveAuthorizationType->fromRepairOrder($repairOrder);

        $recordAuthorization->execute(
            repairOrder: $repairOrder,
            approvalType: $approvalType,
            source: ApprovalSource::from($data['source']),
            approvedBy: $data['approved_by'],
            approvedAmountCents: $approvedAmountCents,
            notes: $data['notes'] ?? null,
            concernDispositions: [],
            actor: $request->user(),
        );

        $totalsCalculator->recalculateRepairOrder($repairOrder->fresh());

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('authorization-rail')
            ->with('status', 'Customer authorization recorded.');
    }
}
