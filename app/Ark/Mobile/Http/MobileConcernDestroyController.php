<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MobileConcernDestroyController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MobileStaffAccess $access,
        EstimateDocumentService $documents,
        OperationalEventRecorder $events,
        RepairOrderConcurrency $concurrency,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($access->canViewRepairOrder($user, $repairOrder), 403);
        abort_unless($access->canSetConcernDisposition($user, $repairOrder), 403);
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        if ($concern->lines()->exists()) {
            throw new HttpException(422, 'Delete or move concern lines before deleting the concern.');
        }

        $payload = [
            'concern_id' => $concern->id,
            'summary' => $concern->summary,
            'recommendation_intent' => $concern->recommendationIntent()->value,
            'position' => $concern->position,
        ];

        $concern->delete();
        $documents->markDirtyForRepairOrder($repairOrder);

        $events->record(
            OperationalEventName::ConcernDeleted,
            $repairOrder,
            actor: $user,
            payload: $payload,
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $user);

        $repairOrder->refresh()->loadMissing([
            'customer',
            'vehicle',
            'assignedTechnician:id,name',
            'concerns.lines',
            'inspection.items.measurements',
            'inspection.items.photos',
            'inspection.items.concern',
        ]);

        return response()->json([
            'repair_order' => $projection->forRepairOrder($repairOrder, $user),
            'message' => 'Concern deleted.',
        ]);
    }
}
