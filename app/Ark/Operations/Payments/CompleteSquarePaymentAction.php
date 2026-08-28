<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Portal\PortalCustomerActivityBroadcaster;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CompleteSquarePaymentAction
{
    public function __construct(
        private readonly RecordLedgerEntryAction $ledger,
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
            throw new RuntimeException('This Square payment attempt is no longer collectible.');
        }

        if ($amountCents !== $attempt->amount_cents) {
            throw new RuntimeException('Square payment amount does not match the initiated attempt.');
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
            $reference = sprintf('Square %s', $squarePaymentId);

            $entries = $this->ledger->recordPayment(
                $repairOrder,
                $amountCents,
                PaymentMethod::Card,
                $actor ?? $attempt->initiatedBy,
                $reference,
                contractPayload: [
                    'square_payment_id' => $squarePaymentId,
                    'capture_surface' => $attempt->capture_surface->value,
                    'gateway_attempt_id' => $attempt->id,
                ],
            );

            $ledgerEntry = $entries[0] ?? null;

            if ($ledgerEntry === null) {
                throw new RuntimeException('Square payment could not be recorded in the ledger.');
            }

            $attempt->forceFill([
                'square_payment_id' => $squarePaymentId,
                'status' => PaymentGatewayAttemptStatus::Completed,
                'processing_fee_cents' => $processingFeeCents,
                'ledger_entry_id' => $ledgerEntry->id,
                'completed_at' => now(),
            ])->save();

            $this->portalInterrupts->broadcastPayment($repairOrder->fresh(), $attempt->refresh());

            return $attempt->refresh();
        });
    }
}
