<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Ark\Operations\LaborGuides\Rte\RteLaborObservationRecorder;
use App\Ark\Runtime\Authorization\PricingAuthority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderLineUpdateController
{
    use RecordsRepairOrderEstimateMutation;
    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderLine $line, EstimateTotalsCalculator $calculator, EstimateDocumentService $documents, OperationalEventRecorder $events, RteLaborObservationRecorder $rteObservations, RepairOrderLinePricing $pricing, RepairOrderConcurrency $concurrency, RefreshCustomerInvoiceAction $refreshInvoice): RedirectResponse
    {
        abort_unless($line->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);
        $this->prepareLaborLineRequest($request);

        $prior = [
            'concern_id' => $line->repair_order_concern_id,
            'type' => $line->type->value,
            'total_cents' => $line->total_cents,
            'labor_hours_overridden' => (bool) $line->labor_hours_overridden,
            'labor_billed_hours' => $line->labor_billed_hours,
            'quantity' => $line->quantity,
        ];

        $validator = validator($request->all(), [
            'repair_order_concern_id' => [
                'required',
                Rule::exists('repair_order_concerns', 'id')->where('repair_order_id', $repairOrder->id),
            ],
            ...RepairOrderWorkGroupLineValidator::attachRules($request, $repairOrder),
            'type' => ['required', Rule::enum(RepairOrderLineType::class)],
            'description' => [
                'required',
                'string',
                Rule::when($request->input('type') === RepairOrderLineType::Note->value, 'max:2000', 'max:255'),
            ],
            'is_private' => ['nullable', 'boolean'],
            'visible_to_advisor' => ['nullable', 'boolean'],
            'visible_to_technician' => ['nullable', 'boolean'],
            'visible_to_customer' => ['nullable', 'boolean'],
            'quantity' => RepairOrderLineType::quantityRules($request),
            'part_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'pricing_mode' => ['nullable', Rule::in(['matrix', 'manual'])],
            'pricing_matrix_key' => ['nullable', 'string', 'max:255'],
            'pricing_matrix_explicit' => ['nullable', 'boolean'],
            'unit_price_override' => ['nullable', 'boolean'],
            'customer_description' => ['nullable', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:255'],
            'sourcing_notes' => ['nullable', 'string', 'max:1000'],
            ...RepairOrderLinePartMetadata::validationRules($request),
            'has_core' => ['nullable', 'boolean'],
            'save_old_part' => ['nullable', 'boolean'],
            'return_mode' => ['nullable', Rule::in(['review'])],
            ...RepairOrderLineLaborInput::rules($request),
        ]);

        RepairOrderWorkGroupLineValidator::validateConcernAlignment($validator, $request);
        $data = $validator->validate();

        $data = RepairOrderLineType::from($data['type'])->applyInputDefaults($data);
        $lineType = RepairOrderLineType::from($data['type']);

        $pricingAttributes = $pricing->attributesFor($data, $repairOrder, $line);
        $this->authorizePricingAuthority($request, $pricingAttributes);

        $quantity = $pricingAttributes['quantity'] ?? $data['quantity'];
        $procurementState = RepairOrderLinePartMetadata::resolveProcurementStateUpdate($data, $line, $lineType->isPart());

        $line->update([
            'repair_order_concern_id' => $data['repair_order_concern_id'],
            'repair_order_work_group_id' => $data['repair_order_work_group_id'] ?? null,
            'type' => $data['type'],
            'description' => $data['description'],
            'customer_description' => $lineType->isPart()
                ? filled($data['customer_description'] ?? null) ? trim((string) $data['customer_description']) : null
                : null,
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
            ...RepairOrderLinePartMetadata::persistenceAttributes($data, $lineType->isPart()),
            ...$lineType->isPart()
                ? RepairOrderLine::resolvedPartPullFlags(
                    $request->boolean('has_core'),
                    $request->boolean('save_old_part'),
                )
                : ['has_core' => false, 'save_old_part' => false],
            ...NoteAudience::fromRequest($request, $lineType)->persistenceAttributes(),
            'subtotal_cents' => $calculator->lineTotalCents($quantity, $pricingAttributes['unit_price_cents']),
            'is_overridden' => $pricingAttributes['is_overridden'],
            ...RepairOrderLineLaborInput::persistenceAttributes($pricingAttributes),
            ...($procurementState instanceof PartProcurementState ? ['procurement_state' => $procurementState] : []),
        ]);

        $calculator->recalculateRepairOrder($repairOrder);
        $documents->markDirtyForRepairOrder($repairOrder);

        $line->refresh();
        $events->record(
            OperationalEventName::EstimateLineUpdated,
            $repairOrder,
            actor: $request->user(),
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
                $rteObservations->recordRecommendationOverridden(
                    repairOrder: $repairOrder,
                    user: $request->user(),
                    line: $line,
                    originalHours: $originalHours,
                    overriddenHours: $overriddenHours,
                );
            }
        }

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        $refreshInvoice->executeIfNeeded(
            $repairOrder->fresh(['concerns', 'lines.concern', 'customer']),
            $request->user(),
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', 'Saved')
            ->with('ark_line_id', $line->id)
            ->withHeaders(['X-ARK-Line-Id' => (string) $line->id]);
    }

    /**
     * @param  array<string, mixed>  $pricingAttributes
     */
    private function authorizePricingAuthority(Request $request, array $pricingAttributes): void
    {
        abort_if(
            ($pricingAttributes['is_overridden'] ?? false) && ! PricingAuthority::allows($request->user()),
            403,
            'Manual pricing overrides require pricing authority.',
        );
    }

    private function prepareLaborLineRequest(Request $request): void
    {
        if ($request->input('type') !== RepairOrderLineType::Labor->value || $request->filled('quantity')) {
            return;
        }

        $request->merge([
            'quantity' => $request->input('labor_entered_hours', '1.00'),
        ]);
    }

}
