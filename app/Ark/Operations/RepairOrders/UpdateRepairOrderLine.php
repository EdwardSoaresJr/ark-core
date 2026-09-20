<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\LaborGuides\Rte\RteLaborObservationRecorder;
use App\Ark\Runtime\Authorization\PricingAuthority;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative estimate line update — shared by operations web and mobile.
 */
final class UpdateRepairOrderLine
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RepairOrderLinePricing $pricing,
        private readonly RteLaborObservationRecorder $rteObservations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RepairOrder $repairOrder, RepairOrderLine $line, array $data, User $actor): RepairOrderLine
    {
        abort_unless((int) $line->repair_order_id === (int) $repairOrder->id, 404);

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

        if ($lineType !== $line->type) {
            throw ValidationException::withMessages([
                'type' => 'Line type cannot change on mobile.',
            ]);
        }

        if ($lineType === RepairOrderLineType::Part
            && blank($validated['part_cost'] ?? null)
            && blank($validated['unit_price'] ?? null)) {
            throw ValidationException::withMessages([
                'part_cost' => 'Enter part cost or sell price.',
            ]);
        }

        $prior = [
            'concern_id' => $line->repair_order_concern_id,
            'type' => $line->type->value,
            'total_cents' => $line->total_cents,
            'labor_hours_overridden' => (bool) $line->labor_hours_overridden,
            'labor_billed_hours' => $line->labor_billed_hours,
            'quantity' => $line->quantity,
        ];

        $validated = $lineType->applyInputDefaults($validated);
        $pricingAttributes = $this->pricing->attributesFor($validated, $repairOrder, $line);

        if (($pricingAttributes['is_overridden'] ?? false) && ! PricingAuthority::allows($actor)) {
            throw ValidationException::withMessages([
                'unit_price' => 'Manual pricing overrides require pricing authority.',
            ]);
        }

        $quantity = $pricingAttributes['quantity'] ?? $validated['quantity'];
        $procurementState = RepairOrderLinePartMetadata::resolveProcurementStateUpdate($validated, $line, $lineType->isPart());

        $line->update([
            'repair_order_concern_id' => $validated['repair_order_concern_id'],
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
            'has_core' => false,
            'save_old_part' => false,
            ...NoteAudience::none()->persistenceAttributes(),
            'is_overridden' => $pricingAttributes['is_overridden'],
            ...RepairOrderLineLaborInput::persistenceAttributes($pricingAttributes),
            'subtotal_cents' => $this->calculator->lineTotalCents($quantity, $pricingAttributes['unit_price_cents']),
            ...($procurementState instanceof PartProcurementState ? ['procurement_state' => $procurementState] : []),
        ]);

        $this->calculator->recalculateRepairOrder($repairOrder);
        $this->documents->markDirtyForRepairOrder($repairOrder);

        $line->refresh();

        $this->events->record(
            OperationalEventName::EstimateLineUpdated,
            $repairOrder,
            actor: $actor,
            payload: [
                'line_id' => $line->id,
                'prior' => $prior,
                'new' => [
                    'concern_id' => $line->repair_order_concern_id,
                    'type' => $line->type->value,
                    'total_cents' => $line->total_cents,
                ],
            ],
        );

        if ($line->type === RepairOrderLineType::Labor
            && $line->labor_hours_overridden
            && ! ($prior['labor_hours_overridden'] ?? false)) {
            $originalHours = (float) ($prior['labor_billed_hours'] ?? $prior['quantity'] ?? 0);
            $overriddenHours = (float) ($line->quantity ?? 0);

            if (abs($overriddenHours - $originalHours) >= 0.005) {
                $this->rteObservations->recordRecommendationOverridden(
                    repairOrder: $repairOrder,
                    user: $actor,
                    line: $line,
                    originalHours: $originalHours,
                    overriddenHours: $overriddenHours,
                );
            }
        }

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
