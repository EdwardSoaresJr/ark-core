<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RepairOrderEvidencePrimaryController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        Evidence $evidence,
        SetPrimaryEvidenceAction $setPrimary,
    ): RedirectResponse {
        abort_unless((int) $evidence->repair_order_id === (int) $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();

        $attachment = $evidence->attachments()->first();
        abort_unless($attachment !== null, 404);

        $setPrimary->handle($repairOrder, $attachment, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('evidence-gallery')
            ->with('status', 'Primary evidence updated.');
    }
}
