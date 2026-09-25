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

        if ($concernDispositions === [] && ! $totalsCalculator->hasApprovedInvoiceableWork($repairOrder)) {
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
            approvedAmountCents: $this->approvedAmountCents($data, $repairOrder, $concernDispositions, $approvalType, $totalsCalculator),
            notes: $data['notes'] ?? null,
            concernDispositions: $concernDispositions,
            actor: $request->user(),
        );

        $totalsCalculator->recalculateRepairOrder($repairOrder->fresh());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('authorization-rail')
            ->with('status', 'Customer authorization recorded.');
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
