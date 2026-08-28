<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Runtime\Authorization\PricingAuthority;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative estimate line create — shared by operations web and mobile.
 */
final class StoreRepairOrderLine
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RepairOrderLifecycleTransition $lifecycle,
        private readonly RepairOrderLinePricing $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(RepairOrder $repairOrder, array $data, User $actor): RepairOrderLine
    {
        $this->prepareLaborQuantity($data);

        $validator = validator($data, [
            'repair_order_concern_id' => [
                'required',
                Rule::exists('repair_order_concerns', 'id')->where('repair_order_id', $repairOrder->id),
            ],
            'type' => ['required', Rule::enum(RepairOrderLineType::class)],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => RepairOrderLineType::quantityRules(new Request($data)),
            'part_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            ...RepairOrderLineLaborInput::rules(new Request($data)),
        ]);

        $validated = $validator->validate();
        $lineType = RepairOrderLineType::from($validated['type']);

        if (! in_array($lineType, [RepairOrderLineType::Labor, RepairOrderLineType::Part], true)) {
            throw ValidationException::withMessages([
                'type' => 'Mobile estimate entry supports labor and parts lines only.',
            ]);
        }

        if ($lineType === RepairOrderLineType::Part
            && blank($validated['part_cost'] ?? null)
            && blank($validated['unit_price'] ?? null)) {
            throw ValidationException::withMessages([
                'part_cost' => 'Enter part cost or sell price.',
            ]);
        }

        $validated = $lineType->applyInputDefaults($validated);
        $pricingAttributes = $this->pricing->attributesFor($validated, $repairOrder);

        if (($pricingAttributes['is_overridden'] ?? false) && ! PricingAuthority::allows($actor)) {
            throw ValidationException::withMessages([
                'unit_price' => 'Manual pricing overrides require pricing authority.',
            ]);
        }

        $quantity = $pricingAttributes['quantity'] ?? $validated['quantity'];

        $line = $repairOrder->lines()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $validated['repair_order_concern_id'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'quantity' => $quantity,
            'unit_price_cents' => $pricingAttributes['unit_price_cents'],
            'part_cost_cents' => $pricingAttributes['part_cost_cents'],
            'matrix_suggested_price_cents' => $pricingAttributes['matrix_suggested_price_cents'],
            'pricing_mode' => $pricingAttributes['pricing_mode'],
            'pricing_matrix_key' => $pricingAttributes['pricing_matrix_key'],
            'pricing_matrix_name' => $pricingAttributes['pricing_matrix_name'],
            'matrix_applied' => $pricingAttributes['matrix_applied'],
            'vendor_name' => $pricingAttributes['vendor_name'],
            'part_number' => $pricingAttributes['part_number'],
            'sourcing_notes' => $pricingAttributes['sourcing_notes'],
            ...RepairOrderLinePartMetadata::persistenceAttributes($validated, $lineType->isPart()),
            'procurement_state' => RepairOrderLinePartMetadata::initialProcurementState($validated, $lineType->isPart()),
            'has_core' => false,
            'save_old_part' => false,
            ...NoteAudience::none()->persistenceAttributes(),
            'is_overridden' => $pricingAttributes['is_overridden'],
            ...RepairOrderLineLaborInput::persistenceAttributes($pricingAttributes),
            'subtotal_cents' => $this->calculator->lineTotalCents($quantity, $pricingAttributes['unit_price_cents']),
        ]);

        $this->calculator->recalculateRepairOrder($repairOrder);

        if (RepairOrderWorkflowStatus::from($repairOrder->status)->is(RepairOrderStatus::Draft)) {
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
            ],
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

        return $line;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function prepareLaborQuantity(array &$data): void
    {
        if (($data['type'] ?? null) !== RepairOrderLineType::Labor->value) {
            return;
        }

        if (filled($data['quantity'] ?? null)) {
            return;
        }

        $data['quantity'] = $data['labor_entered_hours'] ?? '1.00';
    }
}
