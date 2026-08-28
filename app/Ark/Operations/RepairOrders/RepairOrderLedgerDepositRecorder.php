<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Models\User;

class RepairOrderLedgerDepositRecorder
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RecordLedgerEntryAction $ledger,
    ) {}

    public function record(
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod $method,
        ?User $actor = null,
        ?string $reference = null,
    ): RepairOrderLedgerEntry {
        $repairOrder = $repairOrder->fresh();
        $repairOrder->ensureOpenForEditing();

        abort_if($repairOrder->isTerminal(), 422, 'Deposits cannot be recorded on closed repair orders.');

        return $this->ledger->recordDeposit(
            $repairOrder,
            $amountCents,
            $method,
            $actor,
            $reference,
        );
    }
}
