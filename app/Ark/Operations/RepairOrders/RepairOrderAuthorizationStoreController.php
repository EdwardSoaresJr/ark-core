<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Approvals\RecordCustomerAuthorizationAction;
use App\Ark\Operations\Approvals\ResolveStaffAuthorizationType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderAuthorizationStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RecordCustomerAuthorizationAction $recordAuthorization,
        EstimateTotalsCalculator $totalsCalculator,
        ResolveStaffAuthorizationType $resolveAuthorizationType,
        RepairOrderConcurrency $concurrency,
        WorkCompletionAuthorization $workAuthorization,
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

        $concernDispositions = $this->presentedRecommendedDispositions($repairOrder);
        $changedApprovedScopes = array_fill_keys(
            $workAuthorization->approvedConcernIdsChangedSinceApproval($repairOrder),
            RepairOrderConcernDisposition::Approved->value,
        );

        if ($concernDispositions === []
            && $changedApprovedScopes === []
            && ! $totalsCalculator->hasApprovedInvoiceableWork($repairOrder)) {
            return back()
                ->withErrors(['authorization' => 'There is no approved work on this estimate to record.'])
                ->withInput();
        }

        $approvalType = $concernDispositions === []
            ? $resolveAuthorizationType->fromRepairOrder($repairOrder)
            : $resolveAuthorizationType->assumingDispositions($repairOrder, $concernDispositions);

        $recordAuthorization->execute(
            repairOrder: $repairOrder,
            approvalType: $approvalType,
            source: ApprovalSource::from($data['source']),
            approvedBy: $data['approved_by'],
            approvedAmountCents: $changedApprovedScopes !== [] && $this->leftAtChangedScopeDefault($data, $repairOrder, $totalsCalculator, $workAuthorization)
                ? null
                : $this->approvedAmountCents($data, $repairOrder, $concernDispositions, $approvalType, $totalsCalculator),
            notes: $data['notes'] ?? null,
            concernDispositions: $concernDispositions + $changedApprovedScopes,
            actor: $request->user(),
        );

        $totalsCalculator->recalculateRepairOrder($repairOrder->fresh());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('authorization-rail')
            ->with('status', 'Customer authorization recorded.')
            ->with(WorksheetContinuity::SESSION_SCOPE, WorksheetContinuity::declare(
                WorksheetContinuity::AUTHORIZATION,
                WorksheetContinuity::WORKFLOW,
                WorksheetContinuity::SETTLEMENT,
            ));
    }

    /**
     * Recommended work is included only when the estimate has no approved scope yet.
     * Draft, deferred, and declined decisions stay as they are. A later recording
     * does not pull in work that was still recommended after an earlier approval.
     *
     * @return array<int, string>
     */
    private function presentedRecommendedDispositions(RepairOrder $repairOrder): array
    {
        $concerns = $repairOrder->concerns;

        if ($concerns->contains(
            fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved,
        )) {
            return [];
        }

        $dispositions = [];

        foreach ($concerns as $concern) {
            if ($concern->disposition !== RepairOrderConcernDisposition::Recommended) {
                continue;
            }

            $dispositions[$concern->id] = RepairOrderConcernDisposition::Approved->value;
        }

        return $dispositions;
    }

    /**
     * The presented default for approving changed work is the approved total plus that work.
     * Left as presented, the recorded amount is the approved total after this approval.
     *
     * @param  array<string, mixed>  $data
     */
    private function leftAtChangedScopeDefault(
        array $data,
        RepairOrder $repairOrder,
        EstimateTotalsCalculator $totalsCalculator,
        WorkCompletionAuthorization $workAuthorization,
    ): bool {
        if (! isset($data['approved_amount'])) {
            return true;
        }

        $posted = (int) round(((float) $data['approved_amount']) * 100);
        $approvedNow = $totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();

        return in_array($posted, [
            $approvedNow,
            $approvedNow + $workAuthorization->addedWorkOutsideApprovedSalesCents($repairOrder),
        ], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $concernDispositions
     */
    private function approvedAmountCents(
        array $data,
        RepairOrder $repairOrder,
        array $concernDispositions,
        ApprovalType $approvalType,
        EstimateTotalsCalculator $totalsCalculator,
    ): ?int {
        $posted = isset($data['approved_amount'])
            ? (int) round(((float) $data['approved_amount']) * 100)
            : null;

        if ($concernDispositions === []) {
            return $posted;
        }

        $approvedNow = $totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();
        $recommendedNow = $totalsCalculator->recommendedTotalsForRead($repairOrder)->totalCents();
        $leftAsDefault = $posted === null || $posted === $approvedNow || $posted === $recommendedNow;

        if (! $leftAsDefault) {
            return $posted;
        }

        if ($approvalType !== ApprovalType::Diagnostic) {
            return null;
        }

        $totalsCalculator->recalculateRepairOrder($repairOrder);

        return $totalsCalculator
            ->recommendedTotalsForRead($repairOrder->fresh(['lines.concern']))
            ->totalCents();
    }
}
