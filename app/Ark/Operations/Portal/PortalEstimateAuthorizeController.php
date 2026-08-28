<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Approvals\RecordCustomerAuthorizationAction;
use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RepairOrderDefaultDepositCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Brick\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortalEstimateAuthorizeController
{
    public function __invoke(
        Request $request,
        string $token,
        ResolveEstimateAccessTokenAction $resolve,
        PortalEstimateAuthorization $authorization,
        RecordCustomerAuthorizationAction $recordAuthorization,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderDefaultDepositCalculator $defaultDepositCalculator,
        CommunicationEventRecorder $communicationEvents,
    ): RedirectResponse {
        $accessToken = $resolve->execute($token, touchViewed: false);

        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()
            ->with(['customer', 'concerns'])
            ->firstOrFail();

        $mode = $authorization->authorizationMode($repairOrder);

        if ($mode === PortalEstimateAuthorizationMode::None) {
            return redirect()
                ->route('portal.estimates.show', ['token' => $token])
                ->withErrors(['authorization' => 'This estimate is no longer waiting for your approval.']);
        }

        $signatureRequired = ShopSettings::current()->portalSignatureRequired();

        $data = $request->validate([
            'confirmed_name' => ['required', 'string', 'max:255'],
            'concern_dispositions' => ['required', 'array'],
            'concern_dispositions.*' => ['required', Rule::enum(RepairOrderConcernDisposition::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...($signatureRequired ? [
                'signature_data' => ['required', 'string', 'max:500000'],
                'authorization_acknowledged' => ['accepted'],
            ] : []),
        ]);

        $pendingConcerns = $authorization->pendingRecommendedConcerns($repairOrder);
        $pendingIds = $pendingConcerns->pluck('id')->all();

        $concernDispositions = collect($data['concern_dispositions'] ?? [])
            ->mapWithKeys(fn (string $disposition, string|int $concernId): array => [(int) $concernId => $disposition])
            ->filter(fn (string $disposition, int $concernId): bool => in_array($concernId, $pendingIds, true))
            ->all();

        if (count($concernDispositions) !== count($pendingIds)) {
            return back()
                ->withErrors(['concern_dispositions' => 'Choose Approve, Defer, or Decline for each repair.'])
                ->withInput();
        }

        foreach ($concernDispositions as $disposition) {
            if (! in_array($disposition, [
                RepairOrderConcernDisposition::Approved->value,
                RepairOrderConcernDisposition::Deferred->value,
                RepairOrderConcernDisposition::Declined->value,
            ], true)) {
                return back()
                    ->withErrors(['concern_dispositions' => 'Each repair must be approved, deferred, or declined.'])
                    ->withInput();
            }
        }

        $approvedCount = collect($concernDispositions)
            ->filter(fn (string $disposition): bool => $disposition === RepairOrderConcernDisposition::Approved->value)
            ->count();

        $deferredCount = collect($concernDispositions)
            ->filter(fn (string $disposition): bool => $disposition === RepairOrderConcernDisposition::Deferred->value)
            ->count();

        $declinedCount = collect($concernDispositions)
            ->filter(fn (string $disposition): bool => $disposition === RepairOrderConcernDisposition::Declined->value)
            ->count();

        $approvalType = match (true) {
            $approvedCount === 0 => ApprovalType::Partial,
            $approvedCount === count($pendingIds) => ApprovalType::Repair,
            default => ApprovalType::Partial,
        };

        $approval = $recordAuthorization->execute(
            repairOrder: $repairOrder,
            approvalType: $approvalType,
            source: ApprovalSource::Portal,
            approvedBy: trim($data['confirmed_name']),
            approvedAmountCents: null,
            notes: filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            concernDispositions: $concernDispositions,
            signatureDataUrl: filled($data['signature_data'] ?? null) ? trim($data['signature_data']) : null,
        );

        $totalsCalculator->recalculateRepairOrder($repairOrder->fresh());

        $approvedAmountCents = $approval->approved_amount_cents;
        $depositAmountCents = $defaultDepositCalculator->portalDepositCents(
            $repairOrder->fresh(),
            $approvedAmountCents,
        );

        $communicationEvents->record(
            $repairOrder->fresh(),
            OperationalCommunicationType::ApprovalFollowUp,
            OperationalCommunicationChannel::Website,
            OperationalCommunicationDirection::Inbound,
            sprintf(
                'Customer responded via portal (%d approved, %d deferred, %d declined).',
                $approvedCount,
                $deferredCount,
                $declinedCount,
            ),
        );

        return redirect()
            ->route('portal.estimates.show', ['token' => $token])
            ->with('portal_authorization', [
                'approval_id' => $approval->id,
                'approved_amount_cents' => $approvedAmountCents,
                'approved_amount' => Money::ofMinor($approvedAmountCents, 'USD')->formatTo('en_US'),
                'deposit_amount_cents' => $depositAmountCents,
                'deposit_amount' => Money::ofMinor($depositAmountCents, 'USD')->formatTo('en_US'),
                'approved_by' => $approval->approved_by,
                'approved_at_label' => ShopDisplayTimezone::format($approval->approved_at),
                'source' => $approval->source->value,
            ]);
    }
}
