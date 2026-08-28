<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legacy_arksms_concern_id',
    'repair_order_id',
    'summary',
    'scope_entry_kind',
    'scope_entry_concept_id',
    'customer_states',
    'verified_findings',
    'dtcs_summary',
    'recommendation',
    'disposition',
    'production_status',
    'billing_posture',
    'recommendation_intent',
    'position',
])]
class RepairOrderConcern extends Model
{
    protected $touches = ['repairOrder'];

    protected $attributes = [
        'billing_posture' => 'default',
        'recommendation_intent' => 'maintenance',
        'production_status' => 'pending',
        'scope_entry_kind' => 'customer_concern',
    ];

    protected function casts(): array
    {
        return [
            'disposition' => RepairOrderConcernDisposition::class,
            'scope_entry_kind' => ScopeEntryKind::class,
            'production_status' => ScopeProductionStatus::class,
            'billing_posture' => ConcernBillingPostureCast::class,
            'recommendation_intent' => RecommendationIntent::class,
        ];
    }

    protected function summary(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => RepairOrderFreeText::normalize($value),
            set: fn (?string $value): string => RepairOrderFreeText::normalize($value),
        );
    }

    public function recommendationIntent(): RecommendationIntent
    {
        $intent = $this->recommendation_intent;

        return $intent instanceof RecommendationIntent
            ? $intent
            : RecommendationIntent::fromStored(is_string($intent) ? $intent : null);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function scopeEntryConcept(): BelongsTo
    {
        return $this->belongsTo(ScopeEntryConcept::class);
    }

    public function workGroups(): HasMany
    {
        return $this->hasMany(RepairOrderWorkGroup::class)->orderBy('position')->orderBy('id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RepairOrderLine::class)
            ->orderByRaw(RepairOrderLineWorksheetOrder::sqlCaseExpression())
            ->orderBy('id');
    }

    public function ungroupedLines()
    {
        return $this->lines->whereNull('repair_order_work_group_id');
    }

    public function usesRepairActions(): bool
    {
        return $this->workGroups->isNotEmpty();
    }

    public function entryKind(): ScopeEntryKind
    {
        $kind = $this->scope_entry_kind;

        return $kind instanceof ScopeEntryKind
            ? $kind
            : ScopeEntryKind::fromStored(is_string($kind) ? $kind : null);
    }

    public function shouldSurfaceRecommendationStatus(): bool
    {
        if (filled($this->verified_findings) || filled($this->recommendation)) {
            return true;
        }

        if ($this->disposition !== RepairOrderConcernDisposition::Draft) {
            return true;
        }

        return $this->lines->contains(
            fn (RepairOrderLine $line): bool => $line->shouldDisplayOnEstimateWorksheet(),
        );
    }

    public function productionStatus(): ScopeProductionStatus
    {
        $status = $this->production_status;

        return $status instanceof ScopeProductionStatus
            ? $status
            : ScopeProductionStatus::fromStored(is_string($status) ? $status : null);
    }

    /**
     * Bay production tracking (Pending / In Progress / Waiting Parts / Completed).
     * Draft and Recommended must not block In Progress — diagnosis and Testing
     * Package work start before customer authorization. Deferred/Declined stay out.
     */
    public function tracksProduction(): bool
    {
        return $this->disposition->tracksProductionPath();
    }

    /** Flag hours recognize only on customer-authorized scopes. */
    public function earnsFlagRecognition(): bool
    {
        return $this->disposition === RepairOrderConcernDisposition::Approved;
    }

    public function defaultPartsMatrix(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();

        return $this->billing_posture->defaultPartsMatrix($settings);
    }
}
