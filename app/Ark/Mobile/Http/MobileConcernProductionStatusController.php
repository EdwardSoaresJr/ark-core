<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Labor\RecognizeConcernFlagProductionAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MobileConcernProductionStatusController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MobileStaffAccess $access,
        OperationalEventRecorder $events,
        MobileRepairOrderProjection $projection,
        RecognizeConcernFlagProductionAction $recognizeFlagProduction,
    ): JsonResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);
        abort_unless($access->canUpdateConcernProductionStatus($request->user(), $repairOrder), 403);
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();

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
                'surface' => 'mobile',
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

        return response()->json([
            'concern' => $projection->forConcern($repairOrder, $concern, $request->user(), $access),
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
}
