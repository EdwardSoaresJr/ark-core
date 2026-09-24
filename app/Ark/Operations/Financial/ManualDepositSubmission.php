<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLedgerDepositRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ManualDepositSubmission
{
    public function __construct(
        private readonly FinancialSubmissionIntentGate $intents,
        private readonly RepairOrderDepositRecordingGuard $depositGuard,
        private readonly RepairOrderLedgerDepositRecorder $deposits,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        ?User $actor,
        ?string $intentKey,
        int $amountCents,
        PaymentMethod $method,
        ?string $reference,
        bool $broadcastFinancialChange,
    ): FinancialSubmissionClaim {
        $claim = $this->intents->claim(
            $repairOrder,
            FinancialSubmissionOperation::Deposit,
            $intentKey,
            $this->intents->fingerprint(
                FinancialSubmissionOperation::Deposit,
                $repairOrder,
                $amountCents,
                $method,
                $reference,
                null,
            ),
        );

        if ($claim->replayed) {
            return $claim;
        }

        $this->depositGuard->validateAmount($repairOrder, $amountCents);

        $claim = $this->intents->reserve($claim, $repairOrder, FinancialSubmissionOperation::Deposit);

        if ($claim->replayed) {
            return $claim;
        }

        $entry = $this->deposits->record($repairOrder, $amountCents, $method, $actor, $reference);

        $this->intents->complete($claim, $entry);

        if ($broadcastFinancialChange) {
            DB::afterCommit(function () use ($repairOrder, $actor): void {
                app(NotifyRepairOrderFinancialChange::class)->notify(
                    $repairOrder,
                    reason: 'deposit_recorded',
                    actor: $actor,
                );
            });
        }

        return $claim;
    }
}
