<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Idempotent: one alive engine_oil MaintenanceService per RO.
 * Orphaned sessions (concern/package deleted) are cancelled so Add can run again.
 */
final class AddEngineOilServiceAction
{
    public function __construct(
        private readonly ResolveEngineOilPreparedAction $resolvePrepared,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RepairOrderLifecycleTransition $lifecycle,
    ) {}

    /**
     * @return array{service: MaintenanceService, created: bool}
     */
    public function handle(RepairOrder $repairOrder, User $actor, bool $resetReminder = true): array
    {
        if ($repairOrder->vehicle_id === null) {
            throw ValidationException::withMessages([
                'vehicle' => 'Assign a vehicle before adding Engine Oil Service.',
            ]);
        }

        $existing = MaintenanceService::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('kind', MaintenanceServiceKind::EngineOil->value)
            ->whereIn('status', [
                MaintenanceServiceStatus::Active->value,
                MaintenanceServiceStatus::Confirmed->value,
            ])
            ->first();

        if ($existing !== null) {
            if ($existing->isLinkedAlive()) {
                return ['service' => $existing, 'created' => false];
            }

            $existing->markCancelledOrphan();
        }

        $prepared = $this->resolvePrepared->handle((int) $repairOrder->vehicle_id);
        $defaults = EngineOilShopDefaults::fromShopSettings();
        $label = MaintenanceServiceKind::EngineOil->label();

        $service = DB::transaction(function () use (
            $repairOrder,
            $actor,
            $resetReminder,
            $prepared,
            $defaults,
            $label,
        ): MaintenanceService {
            $repairOrder->loadMissing('customer');
            $repairOrder->ensureOpenForEditing();

            $position = ((int) $repairOrder->concerns()->max('position')) + 1;

            $concern = RepairOrderConcern::query()->create([
                'repair_order_id' => $repairOrder->id,
                'summary' => $label,
                'disposition' => RepairOrderConcernDisposition::Recommended,
                'billing_posture' => ConcernBillingPosture::defaultForCustomerTag($repairOrder->customer?->customer_type),
                'recommendation_intent' => RecommendationIntent::Maintenance->value,
                'position' => max(1, $position),
            ]);

            $workGroup = $concern->workGroups()->create([
                'title' => $label,
                'position' => 1,
            ]);

            $line = $repairOrder->lines()->create([
                'repair_order_concern_id' => $concern->id,
                'repair_order_work_group_id' => $workGroup->id,
                'type' => RepairOrderLineType::Package,
                'description' => $label,
                'quantity' => '1.00',
                'unit_price_cents' => $defaults['package_price_cents'],
                'part_cost_cents' => null,
                'matrix_suggested_price_cents' => null,
                'pricing_mode' => null,
                'pricing_matrix_key' => null,
                'pricing_matrix_name' => null,
                'matrix_applied' => false,
                'vendor_name' => null,
                'part_number' => null,
                'sourcing_notes' => null,
                'has_core' => false,
                'save_old_part' => false,
                'is_private' => false,
                'is_overridden' => false,
                'subtotal_cents' => $defaults['package_price_cents'],
            ]);

            $service = MaintenanceService::query()->create([
                'repair_order_id' => $repairOrder->id,
                'vehicle_id' => $repairOrder->vehicle_id,
                'kind' => MaintenanceServiceKind::EngineOil,
                'status' => MaintenanceServiceStatus::Active,
                'repair_order_concern_id' => $concern->id,
                'repair_order_work_group_id' => $workGroup->id,
                'repair_order_line_id' => $line->id,
                'reset_reminder' => $resetReminder,
                'prepared_oil_brand' => $prepared['prepared_oil_brand'],
                'prepared_viscosity' => $prepared['prepared_viscosity'],
                'prepared_quantity_qt' => $prepared['prepared_quantity_qt'],
                'prepared_filter_part' => $prepared['prepared_filter_part'],
                'prepared_washer' => $prepared['prepared_washer'],
                'current_event_id' => null,
            ]);

            $this->calculator->recalculateRepairOrder($repairOrder);

            if (RepairOrderWorkflowStatus::from($repairOrder->status)->is(RepairOrderStatus::Draft)) {
                $this->lifecycle->move($repairOrder, RepairOrderStatus::Estimate, $actor);
            }

            $this->documents->markDirtyForRepairOrder($repairOrder);

            $this->events->record(
                OperationalEventName::EstimateLineAdded,
                $repairOrder,
                actor: $actor,
                payload: [
                    'line_id' => $line->id,
                    'concern_id' => $concern->id,
                    'type' => RepairOrderLineType::Package->value,
                    'maintenance_service_id' => $service->id,
                    'kind' => MaintenanceServiceKind::EngineOil->value,
                    'prepared_source' => $prepared['source'],
                ],
            );

            return $service->fresh() ?? throw new RuntimeException('Maintenance service missing after create.');
        });

        return ['service' => $service, 'created' => true];
    }
}
