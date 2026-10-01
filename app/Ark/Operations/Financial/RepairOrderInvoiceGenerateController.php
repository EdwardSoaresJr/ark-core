<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RepairOrderInvoiceGenerateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        GenerateInvoiceSnapshotAction $generateInvoice,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        try {
            $generateInvoice->execute($repairOrder, $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->back()
                ->withErrors(['invoice' => $exception->getMessage()]);
        }

        $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

        return redirect()
            ->back()
            ->with(
                'status',
                $balance->balanceDueCents === 0
                    ? 'Final invoice issued. Repair order is ready to close after customer handoff.'
                    : 'Final invoice issued. Record payment when ready to collect.',
            );
    }
}
