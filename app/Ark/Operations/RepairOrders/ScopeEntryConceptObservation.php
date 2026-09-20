<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audience-specific language projection linked to operational meaning.
 *
 * Preserves how each participant said it — customer, advisor, technician, invoice.
 * Wording changes; meaning does not.
 */
class ScopeEntryConceptObservation extends Model
{
    protected $fillable = [
        'scope_entry_concept_id',
        'audience',
        'observed_summary',
        'normalized_summary',
        'repair_order_concern_id',
    ];

    protected function casts(): array
    {
        return [
            'audience' => ScopeLanguageAudience::class,
        ];
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(ScopeEntryConcept::class, 'scope_entry_concept_id');
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'repair_order_concern_id');
    }

    public function audienceDialect(): ScopeLanguageAudience
    {
        $audience = $this->audience;

        return $audience instanceof ScopeLanguageAudience
            ? $audience
            : ScopeLanguageAudience::tryFrom((string) $audience) ?? ScopeLanguageAudience::Customer;
    }
}
