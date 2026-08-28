<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class TodayRecommendationCloseLostController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrderLifecycleTransition $lifecycle,
    ): RedirectResponse {
        $validator = Validator::make($request->all(), [
            'repair_order_id' => ['required', 'integer', 'min:1'],
            'lost_reason_key' => ['required', Rule::enum(RepairOrderLostReason::class)],
            'lost_reason_note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('operations.index')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $repairOrder = RepairOrder::query()
            ->where('repair_order_id', (int) $data['repair_order_id'])
            ->firstOrFail();

        $lostReason = RepairOrderLostReason::from($data['lost_reason_key']);
        $lostReasonNote = isset($data['lost_reason_note']) ? trim((string) $data['lost_reason_note']) : null;

        if ($lostReason->requiresNote() && ($lostReasonNote === null || $lostReasonNote === '')) {
            return redirect()
                ->route('operations.index')
                ->withInput()
                ->withErrors([
                    'close_lost' => 'Add a short note when the lost reason is Other.',
                ]);
        }

        $blockingReason = $lifecycle->blockingReason(
            $repairOrder,
            RepairOrderStatus::Closed->value,
            $request->user(),
            'lost',
        );

        if ($blockingReason !== null) {
            return redirect()
                ->route('operations.index')
                ->withInput()
                ->withErrors([
                    'close_lost' => $blockingReason,
                ]);
        }

        $lifecycle->move(
            $repairOrder,
            RepairOrderStatus::Closed->value,
            $request->user(),
            'lost',
            $lostReason,
            $lostReasonNote,
        );

        $this->recordRepairOrderEstimateMutation($repairOrder->fresh(), $request->user());

        return redirect()
            ->route('operations.index')
            ->with(
                'status',
                'RO #'.$repairOrder->repair_order_id.' closed lost — '.$lostReason->label().'.',
            );
    }
}
