<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pre-event cancel: tear down PACKAGE + session together (no orphans).
 */
final class CancelEngineOilServiceAction
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
    ) {}

    public function handle(MaintenanceService $service): void
    {
        if ($service->hasConfirmedEvent() || $service->current_event_id !== null) {
            throw ValidationException::withMessages([
                'service' => 'Confirmed services cannot be cancelled. Use an event correction.',
            ]);
        }

        DB::transaction(function () use ($service): void {
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

            if ($lineId !== null) {
                RepairOrderLine::query()->whereKey($lineId)->delete();
            }

            if ($workGroupId !== null) {
                $service->workGroup()->delete();
            }

            if ($concernId !== null) {
                $service->concern()->delete();
            }

            $this->calculator->recalculateRepairOrder($repairOrder->fresh() ?? $repairOrder);
            $this->documents->markDirtyForRepairOrder($repairOrder);
        });
    }
}
