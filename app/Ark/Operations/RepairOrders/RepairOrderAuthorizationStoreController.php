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
    /** The pending changes the form presented; an approval of changed work covers only those. */
    public const PRESENTED_CHANGES_FIELD = 'presented_changes';

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

        if ($changedApprovedScopes !== [] && ! hash_equals(
            $workAuthorization->pendingChangesFingerprint($repairOrder),
            (string) $request->input(self::PRESENTED_CHANGES_FIELD, ''),
        )) {
            return back()
                ->withErrors(['authorization' => 'The changes awaiting approval are not the ones this form showed. Reload the repair order and confirm the current changes with the customer before recording.'])
                ->withInput();
        }

        if ($changedApprovedScopes !== [] && ! $this->leftAtChangedScopeDefault($data, $repairOrder, $totalsCalculator, $workAuthorization)) {
            $approvedTotals = $totalsCalculator->approvedTotalsForRead($repairOrder);
            $requiredCents = $approvedTotals->totalCents() + $workAuthorization->workOutsideApprovedSalesCents($repairOrder);

            return back()
                ->withErrors([
                    'authorization' => sprintf(
                        'This approval covers the changes awaiting authorization, so the approved total becomes %s. Record that amount, or change the work before recording.',
                        $approvedTotals->format($requiredCents),
                    ),
                    'approved_amount' => sprintf(
                        'Authorized amount must match the work this approval records (%s). Adjust the work selection, or use defer/decline on concerns for a partial-scope approval. Typing a different dollar amount does not authorize a partial line.',
                        $approvedTotals->format($requiredCents),
                    ),
                ])
                ->withInput();
        }

        $approvalType = $concernDispositions === []
            ? $resolveAuthorizationType->fromRepairOrder($repairOrder)
            : $resolveAuthorizationType->assumingDispositions($repairOrder, $concernDispositions);

        // Guard only when this recording authorizes recommended or changed work.
        // Re-documenting already-approved scopes keeps prior documentary-amount behavior.
        if ($concernDispositions !== [] || $changedApprovedScopes !== []) {
            $proposedAuthorizedCents = $this->proposedAuthorizedAmountCents(
                $repairOrder,
                $concernDispositions,
                $changedApprovedScopes,
                $totalsCalculator,
                $workAuthorization,
            );

            if ($mismatch = $this->enteredAmountMismatchMessage(
                $data,
                $repairOrder,
                $approvalType,
                $proposedAuthorizedCents,
                $totalsCalculator,
            )) {
                return back()
                    ->withErrors([
                        'approved_amount' => $mismatch,
                        'authorization' => $mismatch,
                    ])
                    ->withInput();
            }
        }

        $recordAuthorization->execute(
            repairOrder: $repairOrder,
            approvalType: $approvalType,
            source: ApprovalSource::from($data['source']),
            approvedBy: $data['approved_by'],
            // Approving changes records the approved total after them, never a typed or stale amount.
            approvedAmountCents: $changedApprovedScopes !== []
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
     * The presented default for approving changed work is the approved total plus that work, and it
     * records the approved total after this approval. Any other amount, including the approved
     * total before the change, contradicts the work being approved.
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

        return $posted === $approvedNow + $workAuthorization->workOutsideApprovedSalesCents($repairOrder);
    }

    /**
     * Dollars this recording will place on the approval event / authorized scopes.
     *
     * @param  array<int, string>  $concernDispositions
     * @param  array<int, string>  $changedApprovedScopes
     */
    private function proposedAuthorizedAmountCents(
        RepairOrder $repairOrder,
        array $concernDispositions,
        array $changedApprovedScopes,
        EstimateTotalsCalculator $totalsCalculator,
        WorkCompletionAuthorization $workAuthorization,
    ): int {
        if ($changedApprovedScopes !== []) {
            return $totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents()
                + $workAuthorization->workOutsideApprovedSalesCents($repairOrder);
        }

        if ($concernDispositions !== []) {
            return $totalsCalculator->recommendedTotalsForRead($repairOrder)->totalCents();
        }

        return $totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();
    }

    /**
     * Block documentary amounts that disagree with the scopes this form will authorize.
     *
     * Allowed:
     * - blank amount (server fills from authorized scopes)
     * - exact match to the proposed authorized-scope total
     * - diagnostic 0.00 before any approval (existing zero-default → recommended total)
     *
     * Not allowed: submitting the current approved total, a stale recommended total, or
     * any other dollar amount that differs from the scopes this recording authorizes.
     * Matching an older approved/recommended figure must not bypass when the newly
     * authorized concern scope differs.
     *
     * @param  array<string, mixed>  $data
     */
    private function enteredAmountMismatchMessage(
        array $data,
        RepairOrder $repairOrder,
        ApprovalType $approvalType,
        int $proposedAuthorizedCents,
        EstimateTotalsCalculator $totalsCalculator,
    ): ?string {
        if (! isset($data['approved_amount'])) {
            return null;
        }

        $posted = (int) round(((float) $data['approved_amount']) * 100);

        if ($posted === $proposedAuthorizedCents) {
            return null;
        }

        $approvedNow = $totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();

        // Diagnostic estimates historically treat an explicit 0.00 (matching approvedNow
        // before any approval) as "use the recommended total." That is the only
        // non-exact exception; it must not generalize to other approval types or to
        // posting a non-zero approved total that disagrees with the new scope.
        if ($approvalType === ApprovalType::Diagnostic
            && $posted === 0
            && $approvedNow === 0
            && $proposedAuthorizedCents > 0) {
            return null;
        }

        $money = $totalsCalculator->recommendedTotalsForRead($repairOrder);

        return sprintf(
            'Authorized amount must match the work this approval records (%s). You entered %s. Adjust which concerns are recommended versus deferred/declined, or leave the amount at the default. Typing a different dollar amount does not authorize a partial labor or part line.',
            $money->format($proposedAuthorizedCents),
            $money->format($posted),
        );
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

        // Mismatched documentary amounts are rejected before execute(); remaining
        // non-default paths are exact matches already handled above.
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
