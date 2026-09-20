<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderPaymentPostureSync;
use App\Models\User;

class RepairOrderPaymentRecorder
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RecordLedgerEntryAction $ledger,
        private readonly RepairOrderPaymentPostureSync $paymentPosture,
    ) {}

    public function markPaid(RepairOrder $repairOrder, ?User $actor = null): RepairOrder
    {
        $repairOrder = $repairOrder->fresh();

        $repairOrder->ensureOpenForEditing();

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if ($balance->isPaid()) {
            return $repairOrder->refresh();
        }

        abort_unless(
            $balance->hasIssuedInvoice,
            422,
            'Generate the final invoice before recording payment.',
        );

        if ($balance->balanceDueCents <= 0) {
            return $this->paymentPosture->sync($repairOrder);
        }

        $this->ledger->recordPayment(
            $repairOrder,
            $balance->balanceDueCents,
            PaymentMethod::Cash,
            $actor,
        );

        return $this->paymentPosture->sync($repairOrder->fresh());
    }
}
