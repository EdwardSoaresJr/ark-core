<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RepairOrderInvoiceRefreshController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RefreshCustomerInvoiceAction $refreshInvoice,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        try {
            $refreshInvoice->execute($repairOrder, $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->back()
                ->withErrors(['invoice' => $exception->getMessage()]);
        }

        return redirect()
            ->back()
            ->with('status', 'Invoice refreshed to match approved work. Review and send the updated invoice to the customer.');
    }
}
