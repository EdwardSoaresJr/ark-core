<?php

namespace App\Ark\Operations\Commitments;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class OperationalCommitmentFulfillController
{
    public function __invoke(Request $request, OperationalCommitment $commitment): RedirectResponse
    {
        abort_unless($commitment->isOpen(), 404);

        $commitment->load('repairOrder');

        $commitment->forceFill([
            'status' => CommitmentStatus::Fulfilled,
            'fulfilled_at' => now(),
            'fulfilled_by' => $request->user()?->id,
        ])->save();

        $shopRepairOrderId = $commitment->repairOrder?->repair_order_id;

        return redirect()
            ->back()
            ->with(
                'status',
                $shopRepairOrderId !== null
                    ? 'Commitment fulfilled for RO #'.$shopRepairOrderId.'.'
                    : 'Commitment fulfilled.',
            );
    }
}
