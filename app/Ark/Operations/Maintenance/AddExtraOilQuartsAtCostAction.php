<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Extra quarts beyond the sold package — Part line at cost (not PACKAGE mutation).
 */
final class AddExtraOilQuartsAtCostAction
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
    ) {}

    /**
     * @return \App\Ark\Operations\RepairOrders\RepairOrderLine
     */
    public function handle(
        MaintenanceService $service,
        string|float $quarts,
        string|float $costPerQuart,
        ?string $description = null,
    ) {
        if ($service->kind !== MaintenanceServiceKind::EngineOil) {
            throw ValidationException::withMessages([
                'service' => 'Extra quarts only apply to Engine Oil Service.',
            ]);
        }

        if ($service->status === MaintenanceServiceStatus::Cancelled) {
            throw ValidationException::withMessages([
                'service' => 'This maintenance service was cancelled.',
            ]);
        }

        if ($service->repair_order_concern_id === null || $service->repair_order_work_group_id === null) {
            throw ValidationException::withMessages([
                'service' => 'Engine Oil Service is missing its repair action.',
            ]);
        }

        $qty = round((float) $quarts, 2);
        $cost = round((float) $costPerQuart, 2);

        if ($qty <= 0) {
            throw ValidationException::withMessages([
                'quarts' => 'Enter how many extra quarts to bill.',
            ]);
        }

        if ($cost < 0) {
            throw ValidationException::withMessages([
                'cost_per_quart' => 'Cost per quart cannot be negative.',
            ]);
        }

        $label = trim((string) ($description ?? ''));
        if ($label === '') {
            $label = 'Additional oil (beyond package)';
        }

        return DB::transaction(function () use ($service, $qty, $cost, $label) {
            $repairOrder = $service->repairOrder()->firstOrFail();
            $repairOrder->ensureOpenForEditing();

            $costCents = (int) round($cost * 100);
            $qtyStr = number_format($qty, 2, '.', '');

            $line = $repairOrder->lines()->create([
                'repair_order_concern_id' => $service->repair_order_concern_id,
                'repair_order_work_group_id' => $service->repair_order_work_group_id,
                'type' => RepairOrderLineType::Part,
                'description' => $label,
                'quantity' => $qtyStr,
                'unit_price_cents' => $costCents,
                'part_cost_cents' => $costCents,
                'matrix_suggested_price_cents' => null,
                'pricing_mode' => 'manual',
                'pricing_matrix_key' => null,
                'pricing_matrix_name' => null,
                'matrix_applied' => false,
                'vendor_name' => null,
                'part_number' => null,
                'sourcing_notes' => 'Extra quarts beyond Engine Oil Service package — sold at cost.',
                'has_core' => false,
                'save_old_part' => false,
                'is_private' => false,
                'is_overridden' => true,
                'subtotal_cents' => (int) round($qty * $costCents),
            ]);

            $this->calculator->recalculateRepairOrder($repairOrder);
            $this->documents->markDirtyForRepairOrder($repairOrder);

            return $line->fresh() ?? throw new RuntimeException('Extra quarts line missing after create.');
        });
    }
}
