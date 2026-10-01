<?php

namespace App\Ark\Operations\LaborGuides;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderLaborGuideController
{
    public function redirect(
        Request $request,
        RepairOrder $repairOrder,
        string $provider,
        LaborGuideLauncher $launcher,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $repairOrder->refresh();
        $repairOrder->load('vehicle');

        $guide = LaborGuideProvider::fromRoute($provider);
        $concernId = $request->integer('concern_id') ?: null;

        $url = $launcher->launchUrl($repairOrder, $guide, $concernId);

        abort_if($url === null, 422, $launcher->blockedReason($repairOrder, $guide));

        return redirect()->away($url);
    }
}
