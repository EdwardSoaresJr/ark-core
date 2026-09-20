<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RepairOrderPostController
{
    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderPosting $posting): RedirectResponse
    {
        $posting->post($repairOrder, $request->user());

        return back()->with('status', 'repair-order-posted');
    }
}
