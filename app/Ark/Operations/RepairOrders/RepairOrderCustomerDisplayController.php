<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\View\View;

final class RepairOrderCustomerDisplayController
{
    public function __invoke(RepairOrder $repairOrder, CustomerDisplayProjection $projection): View
    {
        abort_unless($repairOrder->lines()->exists(), 404);

        return view('operations.repair-orders.customer-display', [
            'repairOrder' => $repairOrder,
            'display' => $projection->for($repairOrder),
            'refreshSeconds' => 8,
        ]);
    }

    public function fragment(RepairOrder $repairOrder, CustomerDisplayProjection $projection): View
    {
        abort_unless($repairOrder->lines()->exists(), 404);

        return view('operations.repair-orders.partials.customer-display-board', [
            'display' => $projection->for($repairOrder),
        ]);
    }
}
