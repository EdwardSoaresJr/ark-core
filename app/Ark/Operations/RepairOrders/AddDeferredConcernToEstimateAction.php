<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AddDeferredConcernToEstimateAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly RepairOrderLifecycleTransition $lifecycle,
    ) {}

    /**
     * @return array{concern: RepairOrderConcern, created: bool}
     */
    public function handle(RepairOrderConcern $source, RepairOrder $repairOrder, User $actor): array
    {
        $repairOrder->ensureOpenForEditing();
        $source->loadMissing(['repairOrder.customer', 'repairOrder.vehicle', 'lines', 'workGroups.lines']);

        $sourceOrder = $source->repairOrder;

        if ($sourceOrder === null) {
            throw ValidationException::withMessages([
                'concern' => 'That deferred work is missing its repair order.',
            ]);
        }

        if ($source->disposition !== RepairOrderConcernDisposition::Deferred) {
            throw ValidationException::withMessages([
                'concern' => 'Only deferred work can be added this way.',
            ]);
        }

        if ((int) $sourceOrder->id === (int) $repairOrder->id) {
            throw ValidationException::withMessages([
                'concern' => 'That work is already on this repair order.',
            ]);
        }

        if ((int) $sourceOrder->vehicle_id !== (int) $repairOrder->vehicle_id
            || (int) $sourceOrder->customer_id !== (int) $repairOrder->customer_id) {
            throw ValidationException::withMessages([
                'concern' => 'That deferred work does not belong to this vehicle.',
            ]);
        }

        $existing = $repairOrder->concerns
            ->first(function (RepairOrderConcern $concern) use ($source): bool {
                return trim((string) $concern->summary) === trim((string) $source->summary)
                    && trim((string) $concern->recommendation) === trim((string) $source->recommendation);
            });

        if ($existing instanceof RepairOrderConcern) {
            return [
                'concern' => $existing,
                'created' => false,
            ];
        }

        return DB::transaction(function () use ($source, $repairOrder, $actor): array {
            $repairOrder->loadMissing('customer');
            $position = ((int) $repairOrder->concerns()->max('position')) + 1;
            $concern = RepairOrderConcern::query()->create([
                'repair_order_id' => $repairOrder->id,
                'summary' => $source->summary,
                'customer_states' => $source->customer_states,
                'verified_findings' => $source->verified_findings,
                'dtcs_summary' => $source->dtcs_summary,
                'recommendation' => $source->recommendation,
                'disposition' => RepairOrderConcernDisposition::Recommended,
                'production_status' => ScopeProductionStatus::Pending,
                'billing_posture' => $source->billing_posture,
                'recommendation_intent' => $source->recommendation_intent?->value ?? 'maintenance',
                'position' => max(1, $position),
            ]);

            $this->copyWork($source, $concern, $repairOrder);

            $this->calculator->recalculateRepairOrder($repairOrder);

            if (RepairOrderWorkflowStatus::from($repairOrder->status)->is(RepairOrderStatus::Draft)) {
                $this->lifecycle->move($repairOrder, RepairOrderStatus::Estimate, $actor);
            }

            $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

            return [
                'concern' => $concern->fresh(['lines', 'workGroups.lines']) ?? $concern,
                'created' => true,
            ];
        });
    }

    private function copyWork(RepairOrderConcern $source, RepairOrderConcern $target, RepairOrder $repairOrder): void
    {
        foreach ($source->workGroups as $group) {
            $newGroup = $target->workGroups()->create([
                'title' => $group->title,
                'position' => $group->position,
                'owner_type' => RepairActionOwnerType::Technician,
                'owner_user_id' => null,
                'status' => RepairActionStatus::Pending,
                'latest_update' => null,
                'created_from_template_id' => null,
            ]);

            foreach ($group->lines as $line) {
                $this->copyLine($line, $target, $repairOrder, $newGroup->id);
            }
        }

        foreach ($source->lines->whereNull('repair_order_work_group_id') as $line) {
            $this->copyLine($line, $target, $repairOrder, null);
        }
    }

    private function copyLine(
        RepairOrderLine $source,
        RepairOrderConcern $target,
        RepairOrder $repairOrder,
        ?int $workGroupId,
    ): RepairOrderLine {
        $clone = $source->replicate([
            'legacy_arksms_line_id',
            'dealer_quote_line_id',
        ]);
        $clone->repair_order_id = $repairOrder->id;
        $clone->repair_order_concern_id = $target->id;
        $clone->repair_order_work_group_id = $workGroupId;
        $clone->procurement_state = PartProcurementState::None;
        $clone->dealer_quote_line_id = null;
        $clone->save();

        return $clone;
    }
}
