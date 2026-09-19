<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Financial\NotifyRepairOrderFinancialChange;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Portal\PortalCustomerActivityBroadcaster;
use App\Ark\Operations\RepairOrders\RepairOrderLedgerDepositRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CompleteSquareDepositAction
{
    public function __construct(
        private readonly RepairOrderLedgerDepositRecorder $deposits,
        private readonly NotifyRepairOrderFinancialChange $notifyFinancialChange,
        private readonly PortalCustomerActivityBroadcaster $portalInterrupts,
    ) {}

    public function execute(
        PaymentGatewayAttempt $attempt,
        string $squarePaymentId,
        int $amountCents,
        ?int $processingFeeCents = null,
        ?User $actor = null,
    ): PaymentGatewayAttempt {
        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return $attempt;
        }

        if ($attempt->status->isTerminal() && $attempt->status !== PaymentGatewayAttemptStatus::Completed) {
            throw new RuntimeException('This Square deposit attempt is no longer collectible.');
        }

        if ($amountCents !== $attempt->amount_cents) {
            throw new RuntimeException('Square deposit amount does not match the initiated attempt.');
        }

        return DB::transaction(function () use ($attempt, $squarePaymentId, $amountCents, $processingFeeCents, $actor): PaymentGatewayAttempt {
            $attempt = PaymentGatewayAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
                return $attempt;
            }

            $repairOrder = $attempt->repairOrder()->firstOrFail();
            $reference = $attempt->gateway === PaymentGateway::Managed
                ? sprintf('Card deposit %s', $squarePaymentId)
                : sprintf('Square deposit %s', $squarePaymentId);

            $ledgerEntry = $this->deposits->record(
                $repairOrder,
                $amountCents,
                PaymentMethod::Card,
                $actor ?? $attempt->initiatedBy,
                $reference,
            );

            $attempt->forceFill([
                'square_payment_id' => $squarePaymentId,
                'status' => PaymentGatewayAttemptStatus::Completed,
                'processing_fee_cents' => $processingFeeCents,
                'ledger_entry_id' => $ledgerEntry->id,
                'completed_at' => now(),
            ])->save();

            $this->notifyFinancialChange->notify(
                $repairOrder->fresh(),
                reason: 'deposit_received',
                actor: $actor ?? $attempt->initiatedBy,
            );

            $this->portalInterrupts->broadcastPayment($repairOrder->fresh(), $attempt->refresh());

            return $attempt->refresh();
        });
    }
}
