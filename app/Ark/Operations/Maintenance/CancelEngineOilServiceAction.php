<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pre-event cancel: tear down PACKAGE + session together (no orphans).
 */
final class CancelEngineOilServiceAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    public function handle(MaintenanceService $service, ?User $actor = null): void
    {
        if ($service->hasConfirmedEvent() || $service->current_event_id !== null) {
            throw ValidationException::withMessages([
                'service' => 'Confirmed services cannot be cancelled. Use an event correction.',
            ]);
        }

        DB::transaction(function () use ($service, $actor): void {
            $service = MaintenanceService::query()->lockForUpdate()->findOrFail($service->id);
            $repairOrder = $service->repairOrder()->firstOrFail();
            $repairOrder->ensureOpenForEditing();

            $lineId = $service->repair_order_line_id;
            $concernId = $service->repair_order_concern_id;
            $workGroupId = $service->repair_order_work_group_id;

            $service->update([
                'status' => MaintenanceServiceStatus::Cancelled,
                'repair_order_line_id' => null,
                'repair_order_concern_id' => null,
                'repair_order_work_group_id' => null,
            ]);

            $removedEstimateContent = false;

            if ($lineId !== null && RepairOrderLine::query()->whereKey($lineId)->delete() > 0) {
                $removedEstimateContent = true;
            }

            if ($workGroupId !== null && RepairOrderWorkGroup::query()->whereKey($workGroupId)->delete() > 0) {
                $removedEstimateContent = true;
            }

            if ($concernId !== null && RepairOrderConcern::query()->whereKey($concernId)->delete() > 0) {
                $removedEstimateContent = true;
            }

            if (! $removedEstimateContent) {
                return;
            }

            $this->calculator->recalculateRepairOrder($repairOrder->fresh() ?? $repairOrder);
            $this->recordRepairOrderEstimateMutation($repairOrder, $actor);
        });
    }
}
