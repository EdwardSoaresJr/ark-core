<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Waive remaining invoice balance while preserving retail invoice total (Would have cost).
 */
final class WaiveRepairOrderBalanceAction
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RecordLedgerEntryAction $ledger,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        RepairOrderCollectionDisposition $disposition,
        string $reason,
        ?User $actor = null,
    ): RepairOrderLedgerEntry {
        if ($disposition === RepairOrderCollectionDisposition::Retail) {
            throw new InvalidArgumentException('Choose Courtesy, Trade, Goodwill, or Bad debt to waive a balance.');
        }

        $reason = trim($reason);

        if ($disposition->requiresReason() && $reason === '') {
            throw new InvalidArgumentException('Add a short reason so the shop knows why this balance was waived.');
        }

        if ($repairOrder->isTerminal()) {
            throw new RuntimeException('This repair order is closed. Reopen financial closeout before waiving a balance.');
        }

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if (! $balance->hasIssuedInvoice) {
            throw new RuntimeException('Generate the final invoice at retail before waiving the balance.');
        }

        if ($balance->balanceDueCents <= 0) {
            throw new RuntimeException('There is no remaining balance to waive.');
        }

        $amountCents = $balance->balanceDueCents;
        $notes = $disposition->label().($reason !== '' ? ': '.$reason : '');

        return DB::transaction(function () use ($repairOrder, $disposition, $reason, $actor, $amountCents, $notes): RepairOrderLedgerEntry {
            $repairOrder->update([
                'collection_disposition' => $disposition,
                'collection_disposition_reason' => $reason !== '' ? $reason : null,
            ]);

            return $this->ledger->recordWriteOff(
                $repairOrder->fresh(),
                $amountCents,
                $actor,
                $notes,
                reference: $disposition->label(),
            );
        });
    }
}
