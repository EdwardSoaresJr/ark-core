<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reparent a complete concern (work groups + lines) onto a new Draft RO
 * for the same customer/vehicle. Blocked after a final invoice is issued.
 */
final class MoveRepairOrderConcernToNewRepairOrderAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly AssignRepairOrderTechnician $technicianAssignment,
        private readonly RetreatRepairOrderAfterAuthorizationRevocationAction $retreatLifecycle,
    ) {}

    public function execute(RepairOrder $source, RepairOrderConcern $concern, ?User $actor): RepairOrder
    {
        abort_unless($concern->repair_order_id === $source->id, 404);

        $source->ensureOpenForEditing();

        if ($this->balanceDue->forRepairOrder($source)->hasIssuedInvoice) {
            throw ValidationException::withMessages([
                'concern' => 'Cannot move a scope after a final invoice has been issued.',
            ]);
        }

        return DB::transaction(function () use ($source, $concern, $actor): RepairOrder {
            $newRepairOrder = RepairOrder::query()->create([
                'customer_id' => $source->customer_id,
                'vehicle_id' => $source->vehicle_id,
                'status' => RepairOrderStatus::Draft,
                'concern_summary' => $concern->summary !== ''
                    ? $concern->summary
                    : (string) $source->concern_summary,
                'visit_reason' => $source->visit_reason,
                'opened_at' => now(),
            ]);

            $this->events->record(
                OperationalEventName::RepairOrderCreated,
                $newRepairOrder,
                actor: $actor,
                payload: [
                    'customer_id' => $source->customer_id,
                    'vehicle_id' => $source->vehicle_id,
                    'status' => $newRepairOrder->status->value,
                    'source' => 'concern_move',
                    'from_repair_order_id' => $source->id,
                    'concern_id' => $concern->id,
                ],
            );

            if ($source->assigned_technician_id !== null && $actor !== null) {
                $this->technicianAssignment->assign(
                    $newRepairOrder,
                    $source->assigned_technician_id,
                    $actor,
                );
            } else {
                $this->technicianAssignment->assignSoleStaffUserIfApplicable($newRepairOrder, $actor);
            }

            $lineIds = RepairOrderLine::query()
                ->where('repair_order_concern_id', $concern->id)
                ->pluck('id');

            $concern->update([
                'repair_order_id' => $newRepairOrder->id,
                'position' => 1,
            ]);

            if ($lineIds->isNotEmpty()) {
                RepairOrderLine::query()
                    ->whereIn('id', $lineIds)
                    ->update(['repair_order_id' => $newRepairOrder->id]);
            }

            $newRepairOrder = $newRepairOrder->fresh();

            $payload = [
                'concern_id' => $concern->id,
                'summary' => $concern->summary,
                'from_repair_order_id' => $source->id,
                'to_repair_order_id' => $newRepairOrder->id,
                'from_repair_order_number' => $source->repair_order_id,
                'to_repair_order_number' => $newRepairOrder->repair_order_id,
            ];

            $this->events->record(
                OperationalEventName::ConcernMovedToNewRepairOrder,
                $source->fresh(),
                actor: $actor,
                payload: $payload,
            );

            $this->events->record(
                OperationalEventName::ConcernMovedToNewRepairOrder,
                $newRepairOrder,
                actor: $actor,
                payload: $payload,
            );

            $this->totalsCalculator->recalculateRepairOrder($source->fresh());
            $this->totalsCalculator->recalculateRepairOrder($newRepairOrder->fresh());

            $this->documents->markDirtyForRepairOrder($source);
            $this->documents->markDirtyForRepairOrder($newRepairOrder);

            $this->recordRepairOrderEstimateMutation($source->fresh(), $actor);
            $this->recordRepairOrderEstimateMutation($newRepairOrder->fresh(), $actor);

            $this->retreatLifecycle->execute(
                $source->fresh(['concerns', 'lines.concern']),
                $actor,
                'no_approved_work_after_concern_moved',
            );

            return $newRepairOrder->fresh(['customer', 'vehicle', 'concerns', 'lines']);
        });
    }
}
