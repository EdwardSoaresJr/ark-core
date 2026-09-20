<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\DocumentDisclaimerComposer;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Brick\Money\Money;

final class InvoiceSnapshotBuilder
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly DocumentDisclaimerComposer $disclaimerComposer,
        private readonly EstimateSnapshotBuilder $estimateSnapshotBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(RepairOrder $repairOrder, ?User $user = null, bool $skipRecalculate = false): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator', 'concerns.lines', 'lines', 'estimateDocuments.creator']);

        if (! $skipRecalculate) {
            $this->calculator->recalculateRepairOrder($repairOrder);
            $repairOrder->load(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator', 'concerns.lines', 'lines', 'estimateDocuments.creator']);
        }

        $settings = ShopSettings::current();
        $totals = $this->calculator->totalsForApprovedWork($repairOrder);
        $generatedAt = now();

        $approvedConcerns = $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved);

        $advisorName = $repairOrder->serviceAdvisorName();

        return [
            'schema_version' => 1,
            'document_type' => FinancialDocumentType::Invoice->value,
            'generated_at' => $generatedAt->toISOString(),
            'generated_by' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : ($advisorName ? [
                'name' => $advisorName,
            ] : null),
            'repair_order' => [
                'repair_order_id' => $repairOrder->repair_order_id,
                'status' => $repairOrder->status->value,
                'status_label' => $repairOrder->statusDisplayLabel(),
                'advisor_name' => $advisorName,
                'assigned_technician_name' => $repairOrder->technicianOwnershipLabel(),
                'created_at' => $repairOrder->created_at?->toISOString(),
            ],
            'customer' => [
                'id' => $repairOrder->customer->id,
                'name' => $repairOrder->customer->name,
            ],
            'vehicle' => $this->estimateSnapshotBuilder->vehicleIdentitySnapshot($repairOrder),
            'staff' => [
                'execution' => [
                    'technician_name' => $repairOrder->technicianOwnershipLabel(),
                ],
            ],
            'concerns' => $approvedConcerns
                ->map(fn (RepairOrderConcern $concern): array => $this->concernSnapshot($concern, $totals))
                ->values()
                ->all(),
            'totals' => $this->totalsSnapshot(
                $totals,
                $settings,
                StandingDiscountPresentation::label(
                    $repairOrder->customer?->customer_type,
                    $totals->standingDiscountCents(),
                ),
            ),
            'settings' => [
                'tax_label' => $settings->taxLabel(),
                'default_tax_rate' => (string) $settings->default_tax_rate,
                'estimate_disclaimer' => $settings->globalDocumentDisclaimerFor(FinancialDocumentType::Estimate),
                'invoice_disclaimer' => $settings->globalDocumentDisclaimerFor(FinancialDocumentType::Invoice),
                'recommendation_disclaimer' => $settings->recommendation_disclaimer,
                'authorization_language' => $settings->authorizationLanguage(),
            ],
            'documents' => $this->disclaimerComposer->snapshotFor(
                FinancialDocumentType::Invoice,
                $settings,
                $repairOrder->customer?->customer_type,
            ),
            'intake' => [
                'concern_summary' => $repairOrder->concern_summary,
            ],
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
            'recommendation_intent' => $concern->recommendationIntent()->value,
            'recommendation_intent_label' => $concern->recommendationIntent()->staffLabel(),
            'position' => $concern->position,
            'disposition' => $concern->disposition->value,
            'disposition_label' => $concern->disposition->label(),
            'billing_posture' => $concern->billing_posture->value,
            'billing_posture_label' => $concern->billing_posture->label(),
            'subtotal_cents' => $totals->concernSubtotalCents($concern->id),
            'subtotal' => $this->formatCents($totals->concernSubtotalCents($concern->id)),
            'lines' => $concern->lines
                ->filter(fn (RepairOrderLine $line): bool => $line->concern?->disposition === RepairOrderConcernDisposition::Approved && $line->isVisibleToCustomer())
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
            'type' => $line->type->value,
            'type_label' => $line->type->documentLabel(),
            'description' => $line->description,
            'customer_description' => $line->customer_description,
            'customer_description_source' => $line->customer_description_source?->value,
            'quantity' => (string) $line->quantity,
            'unit_price_cents' => $line->unit_price_cents,
            'subtotal_cents' => $line->subtotal_cents,
            'tax_cents' => $line->tax_cents,
            'shop_fee_cents' => $line->shop_fee_cents,
            'standing_discount_cents' => $line->standing_discount_cents,
            'total_cents' => $line->total_cents,
            'vendor_name' => $line->vendor_name,
            'part_number' => $line->part_number,
            'procurement_state_label' => $line->procurementStateLabel(),
            'sourcing_notes' => $line->sourcing_notes,
            'unit_price' => $this->formatCents($line->unit_price_cents),
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
            'standing_discount_cents' => $totals->standingDiscountCents(),
            'tax_cents' => $totals->taxCents(),
            'subtotal_before_tax_cents' => $totals->subtotalBeforeTaxCents(),
            'total_cents' => $totals->totalCents(),
            'tax_label' => $settings->taxLabel(),
            'customer_tax_label' => CustomerEstimateTotalsPresentation::customerTaxLabel($settings),
            'standing_discount_label' => $standingDiscountLabel,
            'labor' => $this->formatCents($totals->laborCents()),
            'parts' => $this->formatCents($totals->partsCents()),
            'fees' => $this->formatCents($totals->feesCents()),
            'standing_discount' => $this->formatCents($totals->standingDiscountCents()),
            'tax' => $this->formatCents($totals->taxCents()),
            'total' => $this->formatCents($totals->totalCents()),
        ];
    }

    private function formatCents(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')
            ->getAmount()
            ->toScale(2)
            ->__toString();
    }

    public static function invoiceTotalCents(array $snapshot): int
    {
        return (int) ($snapshot['totals']['total_cents'] ?? 0);
    }

    /**
     * Stable hash of customer-facing bill content (concerns, lines, intake, totals).
     * Ignores generated_at / actor so living refresh can detect note and copy drift.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function contentFingerprint(array $snapshot): string
    {
        $concerns = collect($snapshot['concerns'] ?? [])
            ->map(function (mixed $concern): array {
                $concern = is_array($concern) ? $concern : [];
                $lines = collect($concern['lines'] ?? [])
                    ->map(function (mixed $line): array {
                        $line = is_array($line) ? $line : [];

                        return [
                            'id' => (int) ($line['id'] ?? 0),
                            'type' => (string) ($line['type'] ?? ''),
                            'description' => (string) ($line['description'] ?? ''),
                            'customer_description' => (string) ($line['customer_description'] ?? ''),
                            'quantity' => (string) ($line['quantity'] ?? ''),
                            'unit_price_cents' => (int) ($line['unit_price_cents'] ?? 0),
                            'subtotal_cents' => (int) ($line['subtotal_cents'] ?? 0),
                            'tax_cents' => (int) ($line['tax_cents'] ?? 0),
                            'shop_fee_cents' => (int) ($line['shop_fee_cents'] ?? 0),
                            'standing_discount_cents' => (int) ($line['standing_discount_cents'] ?? 0),
                            'total_cents' => (int) ($line['total_cents'] ?? 0),
                            'vendor_name' => (string) ($line['vendor_name'] ?? ''),
                            'part_number' => (string) ($line['part_number'] ?? ''),
                            'sourcing_notes' => (string) ($line['sourcing_notes'] ?? ''),
                        ];
                    })
                    ->sortBy('id')
                    ->values()
                    ->all();

                return [
                    'id' => (int) ($concern['id'] ?? 0),
                    'summary' => (string) ($concern['summary'] ?? ''),
                    'position' => (int) ($concern['position'] ?? 0),
                    'disposition' => (string) ($concern['disposition'] ?? ''),
                    'billing_posture' => (string) ($concern['billing_posture'] ?? ''),
                    'subtotal_cents' => (int) ($concern['subtotal_cents'] ?? 0),
                    'lines' => $lines,
                ];
            })
            ->sortBy('id')
            ->values()
            ->all();

        $payload = [
            'intake_concern_summary' => (string) data_get($snapshot, 'intake.concern_summary', ''),
            'totals' => [
                'labor_cents' => (int) data_get($snapshot, 'totals.labor_cents', 0),
                'parts_cents' => (int) data_get($snapshot, 'totals.parts_cents', 0),
                'fees_cents' => (int) data_get($snapshot, 'totals.fees_cents', 0),
                'standing_discount_cents' => (int) data_get($snapshot, 'totals.standing_discount_cents', 0),
                'tax_cents' => (int) data_get($snapshot, 'totals.tax_cents', 0),
                'total_cents' => (int) data_get($snapshot, 'totals.total_cents', 0),
            ],
            'concerns' => $concerns,
        ];

        return hash('xxh3', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>|null  $left
     * @param  array<string, mixed>|null  $right
     */
    public static function contentDiffers(?array $left, ?array $right): bool
    {
        return self::contentFingerprint($left ?? []) !== self::contentFingerprint($right ?? []);
    }
}
