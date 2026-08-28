<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\NotifyRepairOrderFinancialChange;
use App\Ark\Operations\Financial\RepairOrderCollectionDisposition;
use App\Ark\Operations\Financial\WaiveRepairOrderBalanceAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class RepairOrderLedgerWriteOffController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        WaiveRepairOrderBalanceAction $waiveBalance,
        NotifyRepairOrderFinancialChange $notifyFinancialChange,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'disposition' => ['required', 'string', Rule::in(array_map(
                fn (RepairOrderCollectionDisposition $case): string => $case->value,
                RepairOrderCollectionDisposition::waiveOptions(),
            ))],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $disposition = RepairOrderCollectionDisposition::from($data['disposition']);

        try {
            $waiveBalance->execute(
                $repairOrder,
                $disposition,
                (string) $data['reason'],
                $request->user(),
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return redirect()
                ->back()
                ->withErrors(['disposition' => $exception->getMessage()]);
        }

        $notifyFinancialChange->notify($repairOrder->fresh(), reason: 'balance_waived', actor: $request->user());

        return redirect()
            ->back()
            ->with('status', $disposition->label().' — remaining balance waived. Invoice total still shows what this would have cost.');
    }
}
