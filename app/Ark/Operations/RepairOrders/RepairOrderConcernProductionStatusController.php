<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderConcernProductionStatusController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        RepairOrderConcurrency $concurrency,
        UpdateConcernProductionStatusAction $updateProductionStatus,
    ): RedirectResponse|JsonResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersManage->value)
            || $request->user()?->can(ArkCapability::ProductionAccess->value),
            403,
        );

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'production_status' => ['required', Rule::enum(ScopeProductionStatus::class)],
        ]);

        $result = $updateProductionStatus->execute(
            $repairOrder,
            $concern,
            ScopeProductionStatus::from($data['production_status']),
            $request->user(),
        );
        $concern->refresh();
        $recognition = $result['recognition'];

        $statusMessage = 'Scope production status updated.';
        if ($recognition['status'] === 'deferred') {
            $statusMessage = 'Scope marked completed. Flag recognition deferred — assign a technician before production can be recognized.';
        } elseif ($recognition['status'] === 'recognized' && $recognition['recognition'] !== null) {
            $hours = number_format((float) $recognition['recognition']->flag_hours_total, 2);
            $statusMessage = "Scope production status updated. Recognized {$hours} flag hours.";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'production_status' => $concern->productionStatus()->value,
                'label' => $concern->productionStatus()->label(),
                'estimate_version' => $concurrency->openedVersion($repairOrder),
                'flag_recognition' => [
                    'status' => $recognition['status'],
                    'reason' => $recognition['reason'],
                    'recognition_id' => $recognition['recognition']?->id,
                    'flag_hours_total' => $recognition['recognition'] !== null
                        ? (float) $recognition['recognition']->flag_hours_total
                        : null,
                ],
            ]);
        }

        $redirectRoute = $request->input('return_mode') === 'review'
            ? 'operations.repair-orders.estimate-review'
            : 'operations.repair-orders.show';

        return redirect()
            ->route($redirectRoute, $repairOrder)
            ->withFragment('concern-'.$concern->id)
            ->with('status', $statusMessage);
    }
}
