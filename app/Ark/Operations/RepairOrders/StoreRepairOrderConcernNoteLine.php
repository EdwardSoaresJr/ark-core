<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;

final class StoreRepairOrderConcernNoteLine
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RepairOrderLifecycleTransition $lifecycle,
    ) {}

    public function store(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        string $description,
        User $actor,
        bool $isPrivate = true,
    ): RepairOrderLine {
        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();

        $description = trim($description);
        abort_if($description === '', 422, 'Description is required.');

        $line = $repairOrder->lines()->create([
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Note,
            'description' => $description,
            'quantity' => 1,
            'unit_price_cents' => 0,
            'part_cost_cents' => 0,
            'subtotal_cents' => 0,
            ...NoteAudience::fromLegacyPrivate($isPrivate)->persistenceAttributes(),
            'is_overridden' => false,
        ]);

        $this->calculator->recalculateRepairOrder($repairOrder);

        if ($repairOrder->status->is(RepairOrderStatus::Draft)) {
            $this->lifecycle->move($repairOrder, RepairOrderStatus::Estimate, $actor);
        }

        $this->documents->markDirtyForRepairOrder($repairOrder);

        $line->refresh();

        $this->events->record(
            OperationalEventName::EstimateLineAdded,
            $repairOrder,
            actor: $actor,
            payload: [
                'line_id' => $line->id,
                'concern_id' => $line->repair_order_concern_id,
                'type' => $line->type->value,
                'subtotal_cents' => $line->subtotal_cents,
                'total_cents' => $line->total_cents,
                'surface' => 'mobile',
            ],
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

        return $line;
    }

    public function defaultIsPrivate(): bool
    {
        return ShopSettings::current()->default_notes_private;
    }
}
