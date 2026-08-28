<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;

class PartProcurementTransition
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    public function move(RepairOrder $repairOrder, RepairOrderLine $line, PartProcurementState $toState, ?User $actor = null): bool
    {
        abort_unless($line->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        abort_unless($line->isPart(), 422, 'Procurement state only applies to part lines.');

        $fromState = $line->procurementState();

        if ($fromState === $toState) {
            return false;
        }

        abort_if(
            ! in_array($toState, PartProcurementTransitions::nextStatesFor($fromState, $line->part_source), true),
            422,
            'That part state move is not available.',
        );

        if ($toState === PartProcurementState::Ordered && $line->part_source === PartLineSource::CustomerSupplied) {
            abort(422, 'Customer supplied parts are not ordered through the shop.');
        }

        $line->update(['procurement_state' => $toState]);

        if ($toState->eventName() !== null) {
            $this->events->record(
                $toState->eventName(),
                $repairOrder,
                actor: $actor,
                payload: array_filter([
                    'line_id' => $line->id,
                    'concern_id' => $line->repair_order_concern_id,
                    'from_state' => $fromState->value,
                    'to_state' => $toState->value,
                    'part_number' => $line->part_number,
                    'vendor_name' => $line->vendor_name,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            );
        }

        if ($line->repair_order_concern_id !== null) {
            $concern = RepairOrderConcern::query()
                ->whereKey($line->repair_order_concern_id)
                ->with('lines')
                ->first();

            if ($concern instanceof RepairOrderConcern) {
                app(ScopeProductionStatusFromPartsSync::class)->sync($concern, $actor);
            }
        }

        return true;
    }
}
