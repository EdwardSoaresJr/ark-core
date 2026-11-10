<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\EstimatePricing\SynchronizeRepairOrderBillingMinimums;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderConcernBillingPostureController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        ChangeConcernBillingPostureAction $changeBilling,
        RepairOrderConcurrency $concurrency,
        SynchronizeRepairOrderBillingMinimums $billingMinimums,
    ): RedirectResponse|JsonResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'billing_posture' => ['required', Rule::enum(ConcernBillingPosture::class)],
        ]);

        $result = $changeBilling->execute(
            $repairOrder,
            $concern,
            ConcernBillingPosture::from($data['billing_posture']),
            $request->user(),
        );
        $concern->refresh();
        $billingMinimums->execute($repairOrder);

        if ($request->expectsJson()) {
            return response()->json([
                'billing_posture' => $concern->billing_posture->value,
                'label' => $concern->billing_posture->label(),
                'labor_lines_repriced' => $result['repriced'],
                'labor_lines_kept' => $result['kept'],
            ]);
        }

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', self::statusMessage($concern, $result));
    }

    /**
     * @param  array{changed: bool, repriced: int, kept: int}  $result
     */
    private static function statusMessage(RepairOrderConcern $concern, array $result): string
    {
        $message = 'Billing updated for '.$concern->summary.'.';

        if ($result['repriced'] > 0) {
            $message .= ' '.$result['repriced'].' labor line'.($result['repriced'] === 1 ? '' : 's').' repriced at the '.$concern->billing_posture->label().' rate.';
        }

        if ($result['kept'] > 0) {
            $message .= ' '.$result['kept'].' labor line'.($result['kept'] === 1 ? '' : 's').' kept '.($result['kept'] === 1 ? 'its' : 'their').' rate (approved, custom or special pricing).';
        }

        return $message;
    }
}
