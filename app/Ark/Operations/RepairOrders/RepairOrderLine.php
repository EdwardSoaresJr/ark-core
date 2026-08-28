<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Parts\DealerQuoteLine;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'legacy_arksms_line_id',
    'repair_order_id',
    'repair_order_concern_id',
    'repair_order_work_group_id',
    'type',
    'description',
    'customer_description',
    'quantity',
    'unit_price_cents',
    'part_cost_cents',
    'matrix_suggested_price_cents',
    'pricing_mode',
    'pricing_matrix_key',
    'pricing_matrix_name',
    'matrix_applied',
    'vendor_name',
    'part_number',
    'dealer_quote_line_id',
    'is_private',
    'visible_to_advisor',
    'visible_to_technician',
    'visible_to_customer',
    'procurement_state',
    'sourcing_notes',
    'part_source',
    'part_classification',
    'part_warranty_impact',
    'has_core',
    'save_old_part',
    'subtotal_cents',
    'tax_cents',
    'shop_fee_cents',
    'standing_discount_cents',
    'total_cents',
    'is_overridden',
    'labor_category_key',
    'operation_id',
    'labor_category_name',
    'labor_entered_hours',
    'labor_adjustment',
    'labor_adjustment_factor',
    'labor_adjustment_reason',
    'labor_billed_hours',
    'labor_hours_overridden',
    'labor_override_reason',
    'labor_minimum_applied',
    'labor_rate_cents',
    'policy_resolved_labor_rate_cents',
    'resolved_from_posture',
    'resolved_from_operation_class',
    'labor_policy_id',
    'labor_policy_version',
    'labor_rate_override_reason',
    'labor_rate_overridden_at',
    'labor_rate_overridden_by_user_id',
])]
class RepairOrderLine extends Model
{
    protected $touches = ['repairOrder'];

    protected function casts(): array
    {
        return [
            'type' => RepairOrderLineType::class,
            'part_source' => PartLineSource::class,
            'part_classification' => PartLineClassification::class,
            'part_warranty_impact' => PartLineWarrantyImpact::class,
            'procurement_state' => PartProcurementState::class,
            'quantity' => 'decimal:2',
            'labor_entered_hours' => 'decimal:2',
            'labor_adjustment_factor' => 'decimal:2',
            'labor_billed_hours' => 'decimal:2',
            'labor_hours_overridden' => 'boolean',
            'labor_minimum_applied' => 'boolean',
            'labor_rate_cents' => 'integer',
            'policy_resolved_labor_rate_cents' => 'integer',
            'labor_policy_id' => 'integer',
            'labor_policy_version' => 'integer',
            'operation_id' => 'integer',
            'labor_rate_overridden_at' => 'datetime',
            'labor_rate_overridden_by_user_id' => 'integer',
            'unit_price_cents' => 'integer',
            'part_cost_cents' => 'integer',
            'matrix_suggested_price_cents' => 'integer',
            'matrix_applied' => 'boolean',
            'is_private' => 'boolean',
            'visible_to_advisor' => 'boolean',
            'visible_to_technician' => 'boolean',
            'visible_to_customer' => 'boolean',
            'has_core' => 'boolean',
            'save_old_part' => 'boolean',
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'shop_fee_cents' => 'integer',
            'standing_discount_cents' => 'integer',
            'total_cents' => 'integer',
            'is_overridden' => 'boolean',
        ];
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => RepairOrderFreeText::normalize($value),
            set: fn (?string $value): string => RepairOrderFreeText::normalize($value),
        );
    }

    protected function customerDescription(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : RepairOrderFreeText::normalize($value),
            set: fn (?string $value): ?string => $value === null || trim((string) $value) === ''
                ? null
                : RepairOrderFreeText::normalize($value),
        );
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'repair_order_concern_id');
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(RepairOrderWorkGroup::class, 'repair_order_work_group_id');
    }

    public function dealerQuoteLine(): BelongsTo
    {
        return $this->belongsTo(DealerQuoteLine::class, 'dealer_quote_line_id');
    }

    public function isPart(): bool
    {
        return $this->type->isPart();
    }

    public function procurementState(): PartProcurementState
    {
        return $this->procurement_state ?? PartProcurementState::None;
    }

    public function procurementStateLabel(): string
    {
        return $this->procurementState()->label($this->part_source);
    }

    public function procurementNextAction(): string
    {
        return $this->procurementState()->nextActionLabel($this->part_source);
    }

    public function isCustomerSupplied(): bool
    {
        return $this->part_source === PartLineSource::CustomerSupplied;
    }

    /**
     * @return list<PartProcurementState>
     */
    public function availableProcurementTransitions(): array
    {
        return PartProcurementTransitions::nextStates($this);
    }

    public function hasUnresolvedProcurement(): bool
    {
        return $this->isPart() && ! $this->procurementState()->isResolved();
    }

    public function procurementPressureLabel(): string
    {
        return $this->procurementState()->pressureLabel($this->part_source);
    }

    public function grossMarginPercentage(): ?string
    {
        if (! $this->isPart() || $this->part_cost_cents === null || $this->unit_price_cents <= 0) {
            return null;
        }

        return (string) (int) round((($this->unit_price_cents - $this->part_cost_cents) / $this->unit_price_cents) * 100);
    }

    public function matrixMarkupPercentage(): ?string
    {
        if (! $this->isPart() || $this->part_cost_cents === null || $this->matrix_suggested_price_cents === null || $this->part_cost_cents <= 0) {
            return null;
        }

        return rtrim(rtrim(number_format((($this->matrix_suggested_price_cents - $this->part_cost_cents) / $this->part_cost_cents) * 100, 2, '.', ''), '0'), '.');
    }

    public function isAllocatedShopFeeRollupLine(): bool
    {
        if ($this->type !== RepairOrderLineType::Fee) {
            return false;
        }

        return in_array(mb_strtolower(trim($this->description)), [
            'shop supplies',
            'shop supply',
            'shop hazmat',
            'shop/haz',
            'shop hazmat fee',
            'shop supplies fee',
            'shop/hazmat',
        ], true);
    }

    public function shouldDisplayOnEstimateWorksheet(): bool
    {
        if (! $this->isAllocatedShopFeeRollupLine()) {
            return true;
        }

        return ! ShopSettings::current()->shop_fee_enabled;
    }

    public function isPrivateNote(): bool
    {
        return $this->type->isNote() && ! $this->isVisibleToCustomer();
    }

    public function isVisibleToAdvisor(): bool
    {
        if (! $this->type->isNote()) {
            return false;
        }

        if ($this->hasExplicitNoteAudience()) {
            return (bool) $this->visible_to_advisor;
        }

        return true;
    }

    public function isVisibleToTechnician(): bool
    {
        if (! $this->type->isNote()) {
            return false;
        }

        if ($this->hasExplicitNoteAudience()) {
            return (bool) $this->visible_to_technician;
        }

        // Legacy staff notes (is_private only) appeared on the tech sheet.
        return true;
    }

    public function isVisibleToCustomer(): bool
    {
        if (! $this->type->isNote()) {
            return true;
        }

        if ($this->hasExplicitNoteAudience()) {
            return (bool) $this->visible_to_customer;
        }

        return ! (bool) $this->is_private;
    }

    public function noteAudience(): NoteAudience
    {
        return new NoteAudience(
            advisor: $this->isVisibleToAdvisor(),
            technician: $this->isVisibleToTechnician(),
            customer: $this->isVisibleToCustomer(),
        );
    }

    private function hasExplicitNoteAudience(): bool
    {
        return (bool) $this->visible_to_advisor
            || (bool) $this->visible_to_technician
            || (bool) $this->visible_to_customer;
    }

    /**
     * Core parts must be saved for return; save without core is allowed.
     *
     * @return array{has_core: bool, save_old_part: bool}
     */
    public static function resolvedPartPullFlags(bool $hasCore, bool $saveOldPart): array
    {
        return [
            'has_core' => $hasCore,
            'save_old_part' => $saveOldPart || $hasCore,
        ];
    }

    /** @return list<string> */
    public function partMetadataLabels(): array
    {
        if (! $this->type->isPart()) {
            return [];
        }

        $labels = [];

        if ($this->part_source === PartLineSource::CustomerSupplied) {
            $labels[] = $this->part_source->label();
        }

        if ($this->part_classification instanceof PartLineClassification) {
            $labels[] = $this->part_classification->label();
        }

        if ($this->part_warranty_impact !== PartLineWarrantyImpact::None) {
            $labels[] = 'Warranty: '.$this->part_warranty_impact->label();
        }

        return $labels;
    }

    /** @return list<string> */
    public function partPullFlagLabels(): array
    {
        if (! $this->type->isPart()) {
            return [];
        }

        $labels = [];

        if ($this->has_core) {
            $labels[] = 'Core';
        }

        if ($this->save_old_part) {
            $labels[] = 'Save';
        }

        return $labels;
    }
}
