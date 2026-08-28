<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Labor\RecognizeConcernFlagProductionAction;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RepairOrderConcernProductionStatusController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        RepairOrderConcurrency $concurrency,
        OperationalEventRecorder $events,
        RecognizeConcernFlagProductionAction $recognizeFlagProduction,
    ): RedirectResponse|JsonResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersManage->value)
            || $request->user()?->can(ArkCapability::ProductionAccess->value),
            403,
        );

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        if (! $concern->tracksProduction()) {
            throw ValidationException::withMessages([
                'production_status' => 'Production status does not apply to deferred or declined scopes.',
            ]);
        }

        $priorStatus = $concern->productionStatus()->value;

        $data = $request->validate([
            'production_status' => ['required', Rule::enum(ScopeProductionStatus::class)],
        ]);

        $concern->update([
            'production_status' => $data['production_status'],
        ]);

        $concern->refresh();

        $sourceEvent = $events->record(
            OperationalEventName::ConcernProductionStatusChanged,
            $repairOrder,
            actor: $request->user(),
            payload: [
                'concern_id' => $concern->id,
                'prior_production_status' => $priorStatus,
                'new_production_status' => $concern->productionStatus()->value,
            ],
        );

        $recognition = $recognizeFlagProduction->handle(
            $repairOrder->fresh(['assignedTechnician']),
            $concern->fresh(['lines']),
            ScopeProductionStatus::fromStored($priorStatus),
            $concern->productionStatus(),
            $sourceEvent,
            $request->user(),
        );

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
