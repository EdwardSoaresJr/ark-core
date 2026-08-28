<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Str;
use RuntimeException;

final class InitiatePortalEstimateDepositAction
{
    public function __construct(
        private readonly SquareConfiguration $configuration,
        private readonly RepairOrderDepositRecordingGuard $depositGuard,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        EstimateAccessToken $accessToken,
        ApprovalEvent $approval,
    ): PaymentGatewayAttempt {
        abort_unless($this->configuration->portalPayEnabled(), 422, 'Online deposits are not enabled.');

        $repairOrder = $repairOrder->fresh();
        $repairOrder->loadMissing('customer');
        $repairOrder->ensureOpenForEditing();

        abort_unless($approval->visit_id === $repairOrder->id, 422, 'Authorization does not match this repair order.');
        abort_unless($approval->source === ApprovalSource::Portal, 422, 'Authorization does not match this repair order.');
        abort_if($approval->approved_amount_cents <= 0, 422, 'There is no approved amount to deposit.');

        $depositAmountCents = $this->depositGuard->portalChargeCents(
            $repairOrder,
            $approval->approved_amount_cents,
        );

        abort_if($depositAmountCents <= 0, 422, 'Nothing left to collect on this estimate.');

        return PaymentGatewayAttempt::query()->create([
            'repair_order_id' => $repairOrder->id,
            'customer_id' => $repairOrder->customer_id,
            'financial_document_id' => null,
            'gateway' => PaymentGateway::Square,
            'capture_surface' => PaymentCaptureSurface::PortalEstimateDeposit,
            'amount_cents' => $depositAmountCents,
            'currency' => 'USD',
            'idempotency_key' => (string) Str::uuid(),
            'status' => PaymentGatewayAttemptStatus::Pending,
            'initiated_by' => null,
            'estimate_access_token_id' => $accessToken->id,
            'initiated_at' => now(),
        ]);
    }

    public function resolveApproval(RepairOrder $repairOrder, int $approvalId): ApprovalEvent
    {
        $approval = ApprovalEvent::query()
            ->whereKey($approvalId)
            ->where('visit_id', $repairOrder->id)
            ->where('source', ApprovalSource::Portal)
            ->first();

        if (! $approval instanceof ApprovalEvent) {
            throw new RuntimeException('Authorization could not be found.');
        }

        return $approval;
    }
}
