<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\CanonicalBillingClassCatalog;
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
    'billing_inherits_customer',
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
            // Writing a posture without saying how is an explicit choice: "default" follows the
            // customer, anything else is an override. Callers that mean otherwise set both.
            if ($concern->isDirty('billing_posture') && ! $concern->isDirty('billing_inherits_customer')) {
                $concern->billing_inherits_customer = $concern->billingClassKey() === ConcernBillingPosture::Default->value;
            }

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
            'billing_inherits_customer' => 'boolean',
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

    /**
     * A concern on shop default billing follows its customer's billing class, so it uses the parts
     * matrix that class names.
     */
    public function defaultPartsMatrix(?ShopSettings $settings = null, ?Customer $customer = null): array
    {
        $settings ??= ShopSettings::current();
        $repairOrder = $this->repairOrder;
        $context = $repairOrder?->pricingContext($settings);
        $commercial = $context?->commercialProfile();

        $roHasCommercialStamp = is_array($repairOrder?->commercial_pricing_profile)
            && $repairOrder->commercial_pricing_profile !== [];

        $resolveKey = function (?string $className) use ($commercial, $context, $settings, $roHasCommercialStamp): array {
            if ($roHasCommercialStamp) {
                $matrixKey = $commercial?->defaultPartsMatrixKeyFor($className);
                // Class-named keys must match the stamp exactly. Do not silently swap to the
                // stamp default when the named matrix is missing from this RO's freeze.
                if (filled($matrixKey)) {
                    $matrix = $context?->partsMatrixByKey($matrixKey);
                    abort_if(
                        $matrix === null || ($matrix['rows'] ?? []) === [],
                        422,
                        'This repair order\'s frozen pricing does not include the parts matrix required for this billing class. Open a new repair order to use current shop matrices.',
                    );

                    return $matrix;
                }

                $matrix = $context?->partsMatrixByKey(null);

                // Stamped RO: never invent from live shop matrices. Schema-1 without frozen
                // tiers cannot reconstruct historical brackets — fail visibly.
                abort_if(
                    $matrix === null || ($matrix['rows'] ?? []) === [],
                    422,
                    'This repair order\'s frozen pricing does not include a usable parts matrix. Schema-1 stamps without tier definitions cannot reconstruct historical brackets.',
                );

                return $matrix;
            }

            // Living unstamped ROs only: live shop fallback remains until the next
            // matrix/billing activation freezes definitions onto the RO.
            $matrixKey = $commercial?->defaultPartsMatrixKeyFor($className);
            if (filled($matrixKey)) {
                return $context?->partsMatrixByKey($matrixKey)
                    ?? $settings->partsMatrixByKey($matrixKey)
                    ?? $settings->defaultPartsMatrix();
            }

            if ($commercial !== null && $commercial->partsMatrices !== []) {
                return $commercial->partsMatrixByKey($matrixKey)
                    ?? $settings->defaultPartsMatrix();
            }

            return $settings->partsMatrixForCustomerTypeProfile($className) ?? $settings->defaultPartsMatrix();
        };

        if ($this->billing_posture === ConcernBillingPosture::Default) {
            $customerType = ($customer ?? $repairOrder?->customer)?->customer_type;

            return $resolveKey($customerType);
        }

        $billingClass = $this->billing_posture->billingClassName();
        if ($billingClass !== null) {
            return $resolveKey($billingClass);
        }

        if ($roHasCommercialStamp) {
            return $resolveKey(null);
        }

        if ($context !== null && ! $context->allowsLiveCommercialFallback) {
            $matrix = $commercial?->partsMatrixByKey(null);
            abort_if(
                $matrix === null || ($matrix['rows'] ?? []) === [],
                422,
                'This repair order has no recoverable parts matrix. Historical pricing cannot be invented from current shop settings.',
            );

            return $matrix;
        }

        return $this->billing_posture->defaultPartsMatrix($settings);
    }

    /**
     * Attributes for a concern that follows its customer's billing class (the default for every new concern).
     *
     * @return array{billing_posture: string, billing_inherits_customer: bool}
     */
    public static function inheritedBillingAttributes(): array
    {
        return ['billing_posture' => 'default', 'billing_inherits_customer' => true];
    }

    /**
     * Billing attributes to copy onto a duplicate of this concern (inheritance travels with it).
     *
     * @return array{billing_posture: string, billing_inherits_customer: bool|null}
     */
    public function billingAttributesToCopy(): array
    {
        return [
            'billing_posture' => $this->billingClassKey(),
            'billing_inherits_customer' => $this->getAttributes()['billing_inherits_customer'] ?? null,
        ];
    }

    /**
     * True when this concern follows the customer's billing class. NULL flag is legacy: a stored
     * "default" posture means inherit; any other stored posture is an explicit override.
     */
    public function inheritsCustomerBilling(): bool
    {
        $flag = $this->getAttributes()['billing_inherits_customer'] ?? null;

        if ($flag !== null) {
            return (bool) $flag;
        }

        $raw = $this->getAttributes()['billing_posture'] ?? null;

        return ! is_string($raw) || $raw === '' || $raw === ConcernBillingPosture::Default->value;
    }

    /**
     * Raw stored billing class key (built-in posture value or shop-defined custom_* key).
     * For what the concern actually bills as, use {@see self::effectiveBillingClassKey()}.
     */
    public function billingClassKey(): string
    {
        $raw = $this->getAttributes()['billing_posture'] ?? null;

        return is_string($raw) && $raw !== ''
            ? $raw
            : ConcernBillingPosture::Default->value;
    }

    /**
     * The labor column this concern bills as: the customer's class unless explicitly overridden.
     */
    public function effectiveBillingClassKey(): string
    {
        if ($this->inheritsCustomerBilling()) {
            return $this->repairOrder?->customer?->billingClassLaborColumnKey()
                ?? CanonicalBillingClassCatalog::RETAIL_LABOR_COLUMN;
        }

        $raw = $this->billingClassKey();

        return $raw === ConcernBillingPosture::Default->value
            ? CanonicalBillingClassCatalog::RETAIL_LABOR_COLUMN
            : $raw;
    }

    /**
     * Key to hand the labor rate resolver. Inherited Retail leaves the posture to the labor
     * category, exactly as a shop-default concern always has.
     */
    public function laborBillingPostureKey(): string
    {
        $key = $this->effectiveBillingClassKey();

        return $this->inheritsCustomerBilling() && $key === CanonicalBillingClassCatalog::RETAIL_LABOR_COLUMN
            ? ConcernBillingPosture::Default->value
            : $key;
    }

    public function billingClassLabel(): string
    {
        return CanonicalBillingClassCatalog::labelForLaborColumn($this->effectiveBillingClassKey());
    }

    /**
     * "Fleet (Customer default)" or "Warranty (Override)".
     */
    public function billingClassSourceLabel(): string
    {
        return $this->billingClassLabel().($this->inheritsCustomerBilling() ? ' (Customer default)' : ' (Override)');
    }
}
