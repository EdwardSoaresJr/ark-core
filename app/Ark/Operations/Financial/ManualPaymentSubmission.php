<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLedgerPaymentRecorder;
use App\Models\User;
use Illuminate\Support\Carbon;

final class ManualPaymentSubmission
{
    public function __construct(
        private readonly FinancialSubmissionIntentGate $intents,
        private readonly RepairOrderLedgerPaymentRecorder $payments,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        ?User $actor,
        ?string $intentKey,
        int $amountCents,
        PaymentMethod $method,
        ?string $reference,
        ?string $paidOn,
        ?Carbon $paidAt,
    ): FinancialSubmissionClaim {
        $claim = $this->intents->claim(
            $repairOrder,
            FinancialSubmissionOperation::Payment,
            $intentKey,
            $this->intents->fingerprint(
                FinancialSubmissionOperation::Payment,
                $repairOrder,
                $amountCents,
                $method,
                $reference,
                $paidOn,
            ),
        );

        if ($claim->replayed) {
            return $claim;
        }

        $claim = $this->intents->reserve($claim, $repairOrder, FinancialSubmissionOperation::Payment);

        if ($claim->replayed) {
            return $claim;
        }

        $entry = $this->payments->record(
            $repairOrder,
            $amountCents,
            $method,
            $actor,
            $reference,
            $paidAt,
        );

        $this->intents->complete($claim, $entry);

        return $claim;
    }
}
