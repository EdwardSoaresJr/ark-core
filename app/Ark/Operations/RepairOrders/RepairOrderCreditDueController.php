<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\NotifyRepairOrderFinancialChange;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Settles credit due on a revised invoice by refund or store credit. Unlike other ledger changes it
 * stays available after the repair order is closed, so credit owed is never stranded.
 */
class RepairOrderCreditDueController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RecordLedgerEntryAction $ledger,
        EstimateTotalsCalculator $totalsCalculator,
        NotifyRepairOrderFinancialChange $notifyFinancialChange,
        OperationalEventRecorder $events,
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'settle_as' => ['required', Rule::in(['refund', 'store_credit'])],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $asStoreCredit = $data['settle_as'] === 'store_credit';

        try {
            $entry = $ledger->resolveCreditDue(
                $repairOrder,
                $totalsCalculator->unitPriceCents($data['amount']),
                $asStoreCredit,
                $request->user(),
                filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        $events->record(
            OperationalEventName::InvoiceCreditDueResolved,
            $repairOrder,
            actor: $request->user(),
            payload: [
                'ledger_entry_id' => $entry->id,
                'settled_as' => $data['settle_as'],
                'amount_cents' => (int) $entry->amount_cents,
            ],
        );

        $notifyFinancialChange->notify($repairOrder->fresh(), reason: $asStoreCredit ? 'credit_due_store_credit' : 'refund_recorded', actor: $request->user());

        return redirect()
            ->back()
            ->with('status', $asStoreCredit ? 'Credit due issued as store credit.' : 'Refund recorded.');
    }
}
