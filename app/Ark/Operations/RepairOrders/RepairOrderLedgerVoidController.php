<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\NotifyRepairOrderFinancialChange;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Financial\VoidLedgerEntryAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderLedgerVoidController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLedgerEntry $entry,
        VoidLedgerEntryAction $void,
    ): RedirectResponse {
        abort_unless($entry->repair_order_id === $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();

        $void->execute($entry, $request->user());

        return redirect()
            ->back()
            ->with('status', 'Ledger entry voided.');
    }
}
