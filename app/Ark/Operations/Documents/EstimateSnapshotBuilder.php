<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\CustomerEstimateTotalsPresentation;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\StandingDiscountPresentation;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\ApprovalForecastProjection;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\PartLineSource;
use App\Ark\Operations\RepairOrders\PartLineWarrantyImpact;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderFreeText;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Settings\ShopCustomerHoursPresentation;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Timeline\OperationalTimeline;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class EstimateSnapshotBuilder
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly OperationalTimeline $timeline,
        private readonly DocumentDisclaimerComposer $disclaimerComposer,
        private readonly ApprovalForecastProjection $approvalForecast,
    ) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array{shop: array<string, mixed>, settings: array<string, mixed>}
     */
    public function presentationLayers(): array
    {
        $settings = ShopSettings::current();

        return [
            'shop' => $this->shopSnapshot($settings),
            'settings' => $this->settingsSnapshot($settings),
        ];
    }

    public function build(RepairOrder $repairOrder, ?User $user = null): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator', 'concerns.workGroups.lines', 'concerns.lines', 'lines', 'approvalEvents.revocation', 'communicationEvents']);
        $this->calculator->recalculateRepairOrder($repairOrder);
        $repairOrder->load(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator', 'concerns.workGroups.lines', 'concerns.lines', 'lines', 'approvalEvents.revocation', 'communicationEvents']);

        $settings = ShopSettings::current();
        $totals = $this->calculator->totalsFor($repairOrder);
        $generatedAt = now();

        return RepairOrderFreeText::normalizeSnapshot([
            'schema_version' => 2,
            'document_type' => 'estimate',
            'generated_at' => $generatedAt->toISOString(),
            'generated_at_display' => $generatedAt->timezone(config('app.display_timezone'))->format('M j, Y g:i A'),
            'generated_by' => $this->generatedBy($user),
            'shop' => $this->shopSnapshot($settings),
            'settings' => $this->settingsSnapshot($settings),
            'repair_order' => $this->documentRepairOrderSnapshot($repairOrder, $totals),
            'customer' => $this->customerSnapshot($repairOrder),
            'vehicle' => $this->vehicleSnapshot($repairOrder),
            'intake' => [
                'concern_summary' => $repairOrder->concern_summary,
                'visit_reason' => $repairOrder->visit_reason,
            ],
            'concerns' => $repairOrder->concerns
                ->map(fn (RepairOrderConcern $concern): array => $this->concernSnapshot($concern, $totals))
                ->values()
                ->all(),
            'staff' => $this->staffSnapshot($repairOrder),
            'totals' => $this->totalsSnapshot(
                $totals,
                $settings,
                StandingDiscountPresentation::label(
                    $repairOrder->customer?->customer_type,
                    $totals->standingDiscountCents(),
                ),
            ),
            'approval_forecast' => $this->approvalForecast->for($repairOrder),
            'documents' => $this->disclaimerComposer->snapshotFor(
                FinancialDocumentType::Estimate,
                $settings,
                $repairOrder->customer?->customer_type,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function generatedBy(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shopSnapshot(ShopSettings $settings): array
    {
        $logo = $this->logoSnapshot($settings);

        return [
            'name' => $settings->shop_name,
            'phone' => PhoneNumber::display($settings->phone) ?? $settings->phone,
            'email' => $settings->email,
            'website' => $settings->website,
            'logo_path' => $logo['path'],
            'logo_url' => $logo['url'],
            'logo_data_uri' => $logo['data_uri'],
            'address_line_1' => $settings->address_line_1,
            'address_line_2' => $settings->address_line_2,
            'city' => $settings->city,
            'state' => $settings->state,
            'postal_code' => $settings->postal_code,
            'hours_summary' => ShopCustomerHoursPresentation::summaryForShop($settings),
        ];
    }

    /**
     * @return array{path: string|null, url: string|null, data_uri: string|null}
     */
    private function logoSnapshot(ShopSettings $settings): array
    {
        if (! $settings->logo_path || ! Storage::disk('public')->exists($settings->logo_path)) {
            return [
                'path' => null,
                'url' => null,
                'data_uri' => null,
            ];
        }

        $mimeType = Storage::disk('public')->mimeType($settings->logo_path) ?: 'image/png';

        return [
            'path' => $settings->logo_path,
            'url' => Storage::disk('public')->url($settings->logo_path),
            'data_uri' => sprintf(
                'data:%s;base64,%s',
                $mimeType,
                base64_encode(Storage::disk('public')->get($settings->logo_path)),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSnapshot(ShopSettings $settings): array
    {
        return [
            'estimate_disclaimer' => $settings->globalDocumentDisclaimerFor(FinancialDocumentType::Estimate),
            'invoice_disclaimer' => $settings->globalDocumentDisclaimerFor(FinancialDocumentType::Invoice),
            'recommendation_disclaimer' => $settings->recommendation_disclaimer,
            'authorization_language' => $settings->authorizationLanguage(),
            'estimate_validity_days' => $settings->estimate_validity_days,
            'default_labor_rate_cents' => $settings->defaultLaborRateCents(),
            'default_labor_rate' => $this->formatCents($settings->defaultLaborRateCents()),
            'labor_categories' => $settings->laborCategories(),
            'tax_enabled' => $settings->tax_enabled,
            'tax_label' => $settings->taxLabel(),
            'default_tax_rate' => (string) $settings->default_tax_rate,
            'taxable_labor' => $settings->taxable_labor,
            'taxable_parts' => $settings->taxable_parts,
            'taxable_shop_fees' => $settings->taxable_shop_fees,
            'shop_fee_enabled' => $settings->shop_fee_enabled,
            'shop_fee_rate' => (string) $settings->shop_fee_rate,
            'shop_fee_cap_cents' => $settings->shop_fee_cap_cents,
            'shop_fee_cap' => $settings->shop_fee_cap_cents === null
                ? null
                : $this->formatCents($settings->shop_fee_cap_cents),
            'parts_matrices' => $settings->partsMatrices(),
            'default_parts_matrix_key' => collect($settings->partsMatrices())
                ->first(fn (array $matrix): bool => (bool) ($matrix['is_default'] ?? false))['key'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRepairOrderSnapshot(RepairOrder $repairOrder, EstimateTotals $totals): array
    {
        $paymentStatus = $this->balanceDue->forRepairOrder($repairOrder)->isPaid()
            ? RepairOrderPaymentStatus::Paid
            : RepairOrderPaymentStatus::Unpaid;

        return [
            'id' => $repairOrder->id,
            'repair_order_id' => $repairOrder->repair_order_id,
            'status' => $repairOrder->status->value,
            'status_label' => $repairOrder->statusDisplayLabel(),
            'advisor_name' => $repairOrder->serviceAdvisorName(),
            'payment_status' => $paymentStatus->value,
            'payment_status_label' => $paymentStatus->label(),
            'pickup_handoff_label' => $repairOrder->pickupHandoffLabel(),
            'future_work_count' => $repairOrder->futureWorkCount(),
            'future_work_subtotal_cents' => $repairOrder->futureWorkSubtotalCents(),
            'future_work_subtotal' => $totals->format($repairOrder->futureWorkSubtotalCents()),
            'future_work_summary' => $repairOrder->futureWorkSummary(),
            'future_work_next_action' => $repairOrder->futureWorkNextAction(),
            'paid_at' => $paymentStatus === RepairOrderPaymentStatus::Paid
                ? ($repairOrder->paid_at ?? now())->toISOString()
                : null,
            'paid_at_display' => $paymentStatus === RepairOrderPaymentStatus::Paid
                ? ($repairOrder->paid_at ?? now())->timezone(config('app.display_timezone'))->format('M j, Y g:i A')
                : null,
            'created_at' => $repairOrder->created_at?->toISOString(),
            'created_at_display' => $repairOrder->created_at?->timezone(config('app.display_timezone'))->format('M j, Y g:i A'),
            'updated_at' => $repairOrder->updated_at?->toISOString(),
            'assigned_technician_name' => $repairOrder->technicianOwnershipLabel(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffSnapshot(RepairOrder $repairOrder): array
    {
        return [
            'execution' => [
                'technician_name' => $repairOrder->technicianOwnershipLabel(),
                'posture' => $repairOrder->executionPostureLabel(),
                'next_action' => $repairOrder->executionNextAction(),
            ],
            'approval_events' => $repairOrder->approvalEvents
                ->map(fn (ApprovalEvent $approvalEvent): array => $this->approvalEventSnapshot($approvalEvent))
                ->values()
                ->all(),
            'communication' => [
                'posture' => $repairOrder->communicationPostureLabel(),
                'next_action' => $repairOrder->communicationNextAction(),
            ],
            'communications' => $repairOrder->communicationEvents
                ->map(fn (CommunicationEvent $event): array => $this->communicationSnapshot($event))
                ->values()
                ->all(),
            'timeline' => $this->timelineSnapshot($repairOrder),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerSnapshot(RepairOrder $repairOrder): array
    {
        return [
            'id' => $repairOrder->customer->id,
            'name' => $repairOrder->customer->name,
            'first_name' => $repairOrder->customer->first_name,
            'last_name' => $repairOrder->customer->last_name,
            'phone' => $repairOrder->customer->display_phone ?? $repairOrder->customer->phone,
            'email' => $repairOrder->customer->email,
            'contact_preference' => $repairOrder->customer->contact_preference?->value,
            'contact_preference_label' => $repairOrder->customer->preferredContactLabel(),
            'display_address' => $repairOrder->customer->display_address,
            'address_line_1' => $repairOrder->customer->address_line_1,
            'address_line_2' => $repairOrder->customer->address_line_2,
            'city' => $repairOrder->customer->city,
            'state' => $repairOrder->customer->state,
            'postal_code' => $repairOrder->customer->postal_code,
            'customer_type' => $repairOrder->customer->customer_type,
        ];
    }

    /**
     * Vehicle identity for customer-facing documents (estimate, invoice, PDF).
     *
     * @return array<string, mixed>
     */
    public function vehicleIdentitySnapshot(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing('vehicle');

        return $this->vehicleSnapshot($repairOrder);
    }

    /**
     * @return array<string, mixed>
     */
    private function vehicleSnapshot(RepairOrder $repairOrder): array
    {
        return [
            'id' => $repairOrder->vehicle->id,
            'display_name' => $repairOrder->vehicle->display_name,
            'nickname' => $repairOrder->vehicle->nickname,
            'operational_identity' => $repairOrder->vehicle->operational_identity,
            'vin' => $repairOrder->vehicle->authoritativeVin(),
            'has_vin' => $repairOrder->vehicle->hasVin(),
            'plate' => $repairOrder->vehicle->plate,
            'plate_state' => $repairOrder->vehicle->plate_state,
            'year' => $repairOrder->vehicle->year,
            'make' => $repairOrder->vehicle->make,
            'model' => $repairOrder->vehicle->model,
            'trim' => $repairOrder->vehicle->trim,
            'engine' => $repairOrder->vehicle->engine,
            'transmission' => $repairOrder->vehicle->transmission,
            'drive' => $repairOrder->vehicle->drive,
            'color' => $repairOrder->vehicle->color,
            'mileage_in' => $repairOrder->resolvedMileageIn(),
            'mileage_out' => $repairOrder->resolvedMileageOut(),
            'mileage' => $repairOrder->vehicle->legacyOdometerReading(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function concernSnapshot(RepairOrderConcern $concern, EstimateTotals $totals): array
    {
        return [
            'id' => $concern->id,
            'summary' => $concern->summary,
            'notes' => null,
            'customer_states' => $concern->customer_states,
            'verified_findings' => $concern->verified_findings,
            'dtcs_summary' => $concern->dtcs_summary,
            'recommendation' => $concern->recommendation,
            'disposition' => $concern->disposition->value,
            'disposition_label' => $concern->disposition->label(),
            'billing_posture' => $concern->billing_posture->value,
            'billing_posture_label' => $concern->billing_posture->label(),
            'recommendation_intent' => $concern->recommendationIntent()->value,
            'recommendation_intent_label' => $concern->recommendationIntent()->staffLabel(),
            'recommendation_intent_customer_label' => $concern->recommendationIntent()->customerLabel(),
            'position' => $concern->position,
            'subtotal_cents' => $totals->concernSubtotalCents($concern->id),
            'subtotal' => $totals->format($totals->concernSubtotalCents($concern->id)),
            'work_groups' => $concern->workGroups
                ->map(fn (RepairOrderWorkGroup $workGroup): array => $this->workGroupSnapshot($workGroup))
                ->values()
                ->all(),
            'lines' => RepairOrderLineWorksheetOrder::sort(
                $concern->lines->filter(fn (RepairOrderLine $line): bool => $line->shouldDisplayOnEstimateWorksheet())
            )
                ->map(fn (RepairOrderLine $line): array => $this->lineSnapshot($line))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workGroupSnapshot(RepairOrderWorkGroup $workGroup): array
    {
        return [
            'id' => $workGroup->id,
            'title' => $workGroup->title,
            'position' => $workGroup->position,
            'lines' => RepairOrderLineWorksheetOrder::sort(
                $workGroup->lines->filter(fn (RepairOrderLine $line): bool => $line->shouldDisplayOnEstimateWorksheet())
            )
                ->map(fn (RepairOrderLine $line): array => $this->lineSnapshot($line))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineSnapshot(RepairOrderLine $line): array
    {
        return [
            'id' => $line->id,
            'repair_order_concern_id' => $line->repair_order_concern_id,
            'repair_order_work_group_id' => $line->repair_order_work_group_id,
            'type' => $line->type->value,
            'type_label' => $line->type->documentLabel(),
            'description' => $line->description,
            'customer_description' => $line->customer_description,
            'quantity' => (string) $line->quantity,
            'unit_price_cents' => $line->unit_price_cents,
            'part_cost_cents' => $line->part_cost_cents,
            'matrix_suggested_price_cents' => $line->matrix_suggested_price_cents,
            'pricing_mode' => $line->pricing_mode,
            'pricing_matrix_key' => $line->pricing_matrix_key,
            'pricing_matrix_name' => $line->pricing_matrix_name,
            'matrix_applied' => $line->matrix_applied,
            'vendor_name' => $line->vendor_name,
            'part_number' => $line->part_number,
            'sourcing_notes' => $line->sourcing_notes,
            'part_source' => $line->part_source?->value ?? PartLineSource::ShopSupplied->value,
            'part_source_label' => $line->part_source?->label() ?? PartLineSource::ShopSupplied->label(),
            'part_classification' => $line->part_classification?->value,
            'part_classification_label' => $line->part_classification?->label(),
            'part_warranty_impact' => $line->part_warranty_impact?->value ?? PartLineWarrantyImpact::None->value,
            'part_warranty_impact_label' => $line->part_warranty_impact?->label() ?? PartLineWarrantyImpact::None->label(),
            'procurement_state' => $line->procurementState()->value,
            'procurement_state_label' => $line->procurementStateLabel(),
            'procurement_next_action' => $line->procurementNextAction(),
            'subtotal_cents' => $line->subtotal_cents,
            'tax_cents' => $line->tax_cents,
            'shop_fee_cents' => $line->shop_fee_cents,
            'standing_discount_cents' => $line->standing_discount_cents,
            'total_cents' => $line->total_cents,
            'is_overridden' => $line->is_overridden,
            'labor_category_key' => $line->labor_category_key,
            'labor_category_name' => $line->labor_category_name,
            'labor_entered_hours' => $line->labor_entered_hours === null ? null : (string) $line->labor_entered_hours,
            'labor_adjustment' => $line->labor_adjustment,
            'labor_adjustment_factor' => $line->labor_adjustment_factor === null ? null : (string) $line->labor_adjustment_factor,
            'labor_adjustment_reason' => $line->labor_adjustment_reason,
            'labor_billed_hours' => $line->labor_billed_hours === null ? null : (string) $line->labor_billed_hours,
            'labor_hours_overridden' => (bool) $line->labor_hours_overridden,
            'labor_override_reason' => $line->labor_override_reason,
            'labor_minimum_applied' => (bool) $line->labor_minimum_applied,
            'labor_rate_cents' => $line->labor_rate_cents,
            'labor_rate' => $line->labor_rate_cents === null ? null : $this->formatCents($line->labor_rate_cents),
            'is_private' => $line->isPrivateNote(),
            'visible_to_advisor' => $line->type->isNote() ? $line->isVisibleToAdvisor() : false,
            'visible_to_technician' => $line->type->isNote() ? $line->isVisibleToTechnician() : false,
            'visible_to_customer' => $line->type->isNote() ? $line->isVisibleToCustomer() : false,
            'unit_price' => $this->formatCents($line->unit_price_cents),
            'part_cost' => $line->part_cost_cents === null ? null : $this->formatCents($line->part_cost_cents),
            'subtotal' => $this->formatCents($line->subtotal_cents),
            'tax' => $this->formatCents($line->tax_cents),
            'shop_fee' => $this->formatCents($line->shop_fee_cents),
            'standing_discount' => $this->formatCents($line->standing_discount_cents),
            'total' => $this->formatCents($line->total_cents),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function totalsSnapshot(EstimateTotals $totals, ShopSettings $settings, ?string $standingDiscountLabel): array
    {
        return [
            'gross_labor_cents' => $totals->grossLaborCents(),
            'gross_parts_cents' => $totals->grossPartsCents(),
            'labor_cents' => $totals->laborCents(),
            'parts_cents' => $totals->partsCents(),
            'fees_cents' => $totals->feesCents(),
            'allocated_shop_fees_cents' => $totals->allocatedShopFeesCents(),
            'standing_discount_cents' => $totals->standingDiscountCents(),
            'taxable_sell_cents' => $totals->taxableSellCents(),
            'tax_cents' => $totals->taxCents(),
            'subtotal_before_tax_cents' => $totals->subtotalBeforeTaxCents(),
            'total_cents' => $totals->totalCents(),
            'tax_label' => $settings->taxLabel(),
            'customer_tax_label' => CustomerEstimateTotalsPresentation::customerTaxLabel($settings),
            'standing_discount_label' => $standingDiscountLabel,
            'labor' => $totals->format($totals->laborCents()),
            'parts' => $totals->format($totals->partsCents()),
            'fees' => $totals->format($totals->feesCents()),
            'standing_discount' => $totals->format($totals->standingDiscountCents()),
            'taxable_sell' => $totals->format($totals->taxableSellCents()),
            'tax' => $totals->format($totals->taxCents()),
            'total' => $totals->format($totals->totalCents()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalEventSnapshot(ApprovalEvent $approvalEvent): array
    {
        $approvalEvent->loadMissing('revocation');

        return [
            'id' => $approvalEvent->id,
            'approval_type' => $approvalEvent->approval_type->value,
            'approval_type_label' => $approvalEvent->approval_type->label(),
            'approved_amount_cents' => $approvalEvent->approved_amount_cents,
            'approved_amount' => $this->formatCents($approvalEvent->approved_amount_cents),
            'source' => $approvalEvent->source->value,
            'source_label' => $approvalEvent->source->label(),
            'approved_by' => $approvalEvent->approved_by,
            'approved_at' => $approvalEvent->approved_at?->toISOString(),
            'approved_at_display' => $approvalEvent->approved_at?->timezone(config('app.display_timezone'))->format('M j, Y g:i A'),
            'notes' => $approvalEvent->notes,
            'revoked' => $approvalEvent->isRevoked(),
            'revocation' => $approvalEvent->revocation ? [
                'source_label' => $approvalEvent->revocation->source->label(),
                'revoked_by' => $approvalEvent->revocation->revoked_by,
                'revoked_at_display' => $approvalEvent->revocation->revoked_at?->timezone(config('app.display_timezone'))->format('M j, Y g:i A'),
                'notes' => $approvalEvent->revocation->notes,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function communicationSnapshot(CommunicationEvent $event): array
    {
        return [
            'id' => $event->id,
            'communication_type' => $event->event_type->value,
            'communication_type_label' => $event->event_type->label(),
            'channel' => $event->channel->value,
            'channel_label' => $event->channel->label(),
            'direction' => $event->direction->value,
            'direction_label' => $event->direction->label(),
            'summary' => $event->summary,
            'occurred_at' => $event->occurred_at?->toISOString(),
            'occurred_at_display' => $event->occurred_at?->timezone(config('app.display_timezone'))->format('M j, Y g:i A'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timelineSnapshot(RepairOrder $repairOrder): array
    {
        return $this->timeline
            ->forRepairOrder($repairOrder, 6)
            ->map(fn (array $entry): array => [
                'title' => $entry['title'],
                'detail' => $entry['detail'],
                'actor' => $entry['actor'],
                'tone' => $entry['tone'],
                'occurred_at' => $entry['occurred_at']?->toISOString(),
                'occurred_at_display' => $entry['occurred_at']?->timezone(config('app.display_timezone'))->format('M j, Y g:i A'),
            ])
            ->values()
            ->all();
    }

    private function formatCents(?int $cents): string
    {
        return '$'.number_format(($cents ?? 0) / 100, 2);
    }
}
