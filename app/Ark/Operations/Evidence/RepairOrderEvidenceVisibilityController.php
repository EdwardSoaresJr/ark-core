<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderEvidenceVisibilityController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        Evidence $evidence,
        ChangeEvidenceVisibilityAction $changeVisibility,
    ): RedirectResponse {
        abort_unless((int) $evidence->repair_order_id === (int) $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'visibility' => ['required', Rule::enum(EvidenceVisibility::class)],
        ]);

        $changeVisibility->handle(
            $evidence,
            EvidenceVisibility::from($data['visibility']),
            $request->user(),
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('evidence-gallery')
            ->with('status', 'Evidence visibility updated.');
    }
}
