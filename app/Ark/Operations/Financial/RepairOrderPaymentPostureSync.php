<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use Illuminate\Support\Carbon;

/**
 * TEMPORARY COMPATIBILITY LAYER — remove after financial workflow UI owns payment posture.
 *
 * Ledger + BalanceDueCalculator are authoritative. This class mirrors balance into
 * repair_orders.payment_status and paid_at so legacy queue/report surfaces keep working.
 *
 * Do not add new callers. Do not treat payment_status as financial truth.
 *
 * @see docs/ARK-FINANCIAL-AUTHORITY-AND-CLOSEOUT.md
 */
final class RepairOrderPaymentPostureSync
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    public function sync(RepairOrder $repairOrder): RepairOrder
    {
        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        $attributes = [
            'payment_status' => $balance->isPaid()
                ? RepairOrderPaymentStatus::Paid
                : RepairOrderPaymentStatus::Unpaid,
            'paid_at' => $balance->isPaid() ? $this->paidAtForRepairOrder($repairOrder) : null,
        ];

        $repairOrder->forceFill($attributes)->save();

        $invoice = $this->balanceDue->issuedInvoice($repairOrder);
        if ($invoice !== null) {
            $this->balanceDue->syncInvoiceStatus($invoice);
        }

        return $repairOrder->refresh();
    }

    private function paidAtForRepairOrder(RepairOrder $repairOrder): Carbon
    {
        $latestPaymentAt = RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('entry_type', LedgerEntryType::Payment)
            ->active()
            ->max('recorded_at');

        if ($latestPaymentAt !== null) {
            return Carbon::parse($latestPaymentAt);
        }

        return now();
    }
}
