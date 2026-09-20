<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderPaymentPostureSync;
use App\Models\User;
use Illuminate\Support\Carbon;

class RepairOrderLedgerPaymentRecorder
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RecordLedgerEntryAction $ledger,
        private readonly RepairOrderPaymentPostureSync $paymentPosture,
    ) {}

    public function record(
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod $method,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $paidAt = null,
    ): RepairOrder {
        $repairOrder = $repairOrder->fresh();
        $repairOrder->ensureOpenForEditing();

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        abort_unless(
            $balance->hasIssuedInvoice,
            422,
            'Generate the final invoice before recording payment.',
        );

        $this->ledger->recordPayment($repairOrder, $amountCents, $method, $actor, $reference, $paidAt);

        return $this->paymentPosture->sync($repairOrder->fresh());
    }
}
