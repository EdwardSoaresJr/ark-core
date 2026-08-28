<?php

namespace App\Ark\Operations\WorkAuthorization;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Authorize a Testing Package on a repair order.
 * Creates Concern → Repair Action (work group) → Package line ($0 until Pricing Policy) → WorkAuthorization.
 * No Level labels. No rates. Permission only.
 */
final class AuthorizeTestingPackageAction
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
    ) {}

    public function handle(RepairOrder $repairOrder, User $actor, ?RepairOrderConcern $attachToConcern = null): WorkAuthorization
    {
        $repairOrder->ensureOpenForEditing();
        $repairOrder->loadMissing('customer');

        return DB::transaction(function () use ($repairOrder, $actor, $attachToConcern): WorkAuthorization {
            $label = WorkAuthorizationPackageType::Testing->label();

            if ($attachToConcern !== null) {
                $concern = $attachToConcern;
                abort_unless(
                    (int) $concern->repair_order_id === (int) $repairOrder->id,
                    404,
                );
            } else {
                $position = ((int) $repairOrder->concerns()->max('position')) + 1;
                $concern = RepairOrderConcern::query()->create([
                    'repair_order_id' => $repairOrder->id,
                    'summary' => $label,
                    'disposition' => RepairOrderConcernDisposition::Recommended,
                    'billing_posture' => ConcernBillingPosture::defaultForCustomerTag($repairOrder->customer?->customer_type),
                    'recommendation_intent' => RecommendationIntent::Diagnostic->value,
                    'position' => max(1, $position),
                ]);
            }

            $workGroupPosition = ((int) $concern->workGroups()->max('position')) + 1;
            $workGroup = $concern->workGroups()->create([
                'title' => $label,
                'position' => max(1, $workGroupPosition),
            ]);

            // Package sell line exists so Estimate can later hold a price — $0 until Pricing Policy.
            $line = $repairOrder->lines()->create([
                'repair_order_concern_id' => $concern->id,
                'repair_order_work_group_id' => $workGroup->id,
                'type' => RepairOrderLineType::Package,
                'description' => $label,
                'quantity' => '1.00',
                'unit_price_cents' => 0,
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
                'subtotal_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => 0,
            ]);

            $authorization = WorkAuthorization::query()->create([
                'repair_order_id' => $repairOrder->id,
                'package_type' => WorkAuthorizationPackageType::Testing,
                'status' => WorkAuthorizationStatus::Authorized,
                'scope_key' => null,
                'repair_order_concern_id' => $concern->id,
                'repair_order_work_group_id' => $workGroup->id,
                'repair_order_line_id' => $line->id,
                'authorized_by_user_id' => $actor->id,
                'authorized_at' => now(),
            ]);

            $this->calculator->recalculateRepairOrder($repairOrder->fresh());
            $this->documents->markDirtyForRepairOrder($repairOrder->fresh());

            $this->events->record(
                OperationalEventName::WorkAuthorizationCreated,
                $repairOrder,
                actor: $actor,
                payload: [
                    'work_authorization_id' => $authorization->id,
                    'package_type' => WorkAuthorizationPackageType::Testing->value,
                    'repair_order_concern_id' => $concern->id,
                    'repair_order_work_group_id' => $workGroup->id,
                    'line_id' => $line->id,
                ],
            );

            return $authorization->fresh(['concern', 'workGroup', 'packageLine'])
                ?? throw new \RuntimeException('Work authorization missing after create.');
        });
    }
}
