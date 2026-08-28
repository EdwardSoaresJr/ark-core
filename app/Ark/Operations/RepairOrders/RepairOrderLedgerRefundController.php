<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\NotifyRepairOrderFinancialChange;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderLedgerRefundController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RecordLedgerEntryAction $ledger,
        EstimateTotalsCalculator $totalsCalculator,
        NotifyRepairOrderFinancialChange $notifyFinancialChange,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $amountCents = $totalsCalculator->unitPriceCents($data['amount']);

        $ledger->recordRefund(
            $repairOrder,
            $amountCents,
            $request->user(),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
        );

        $notifyFinancialChange->notify($repairOrder->fresh(), reason: 'refund_recorded', actor: $request->user());

        return redirect()
            ->back()
            ->with('status', 'Refund recorded.');
    }
}
