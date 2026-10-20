<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
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
    'recommendation_priority',
    'information_only',
    'position',
])]
class RepairOrderConcern extends Model
{
    protected $touches = ['repairOrder'];

    protected $attributes = [
        'billing_posture' => 'default',
        'recommendation_intent' => 'maintenance',
        'information_only' => false,
        'production_status' => 'pending',
        'scope_entry_kind' => 'customer_concern',
    ];

    protected static function booted(): void
    {
        static::saving(function (RepairOrderConcern $concern): void {
            if ($concern->isInformationOnly()) {
                $concern->recommendation_priority = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'disposition' => RepairOrderConcernDisposition::class,
            'scope_entry_kind' => ScopeEntryKind::class,
            'production_status' => ScopeProductionStatus::class,
            'billing_posture' => ConcernBillingPostureCast::class,
            'recommendation_intent' => RecommendationIntentCast::class,
            'recommendation_priority' => RecommendationPriority::class,
            'information_only' => 'boolean',
        ];
    }

    protected function summary(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => RepairOrderFreeText::normalize($value),
            set: fn (?string $value): string => RepairOrderFreeText::normalize($value),
        );
    }

    public function recommendationIntent(): ?RecommendationIntent
    {
        $intent = $this->recommendation_intent;

        return $intent instanceof RecommendationIntent
            ? $intent
            : RecommendationIntent::trySemantic(is_string($intent) ? $intent : null);
    }

    public function recommendationPriority(): ?RecommendationPriority
    {
        $priority = $this->recommendation_priority;

        return $priority instanceof RecommendationPriority
            ? $priority
            : RecommendationPriority::tryFromStored(is_string($priority) ? $priority : null);
    }

    public function isInformationOnly(): bool
    {
        return (bool) $this->information_only;
    }

    public function presentation(): RecommendationPresentation
    {
        return RecommendationPresentation::forConcern($this);
    }

    /**
     * @return array{recommendation_priority: ?string, information_only: bool, recommendation_intent: ?string}
     */
    public function recommendationAttributes(): array
    {
        return [
            'recommendation_priority' => $this->isInformationOnly() ? null : $this->recommendationPriority()?->value,
            'information_only' => $this->isInformationOnly(),
            'recommendation_intent' => $this->recommendationIntent()?->value,
        ];
    }

    /**
     * @return array{
     *     recommendation_priority: ?string,
     *     information_only: bool,
     *     recommendation_intent: ?string,
     *     recommendation_intent_label: ?string,
     *     recommendation_intent_customer_label: ?string
     * }
     */
    public function recommendationDocumentAttributes(): array
    {
        $presentation = $this->presentation();
        $semantic = $this->recommendationIntent();

        return [
            ...$this->recommendationAttributes(),
            'recommendation_intent_label' => $semantic?->staffLabel(),
            'recommendation_intent_customer_label' => $presentation->customerLabel(),
        ];
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

    public function authorizationExceptions(): HasMany
    {
        return $this->hasMany(AuthorizationException::class)->orderBy('id');
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    public function linesEligibleForAuthorizationException(): Collection
    {
        return $this->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart() || $line->type->isLabor())
            ->values();
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
     * Draft and Recommended must not block In Progress - diagnosis and Testing
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
