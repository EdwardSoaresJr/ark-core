<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Operational meaning authority — stable meaning that survives every wording.
 *
 * "Front Brake Service" is not just a label. It is the meaning that persists
 * across customer, advisor, technician, invoice, search, voice, and time.
 * Wording per audience lives in observations (projections).
 *
 * Observed language → operational meaning → audience projection.
 *
 * Invariant: did this preserve meaning, or merely preserve data?
 */
class ScopeEntryConcept extends Model
{
    protected $fillable = [
        'canonical_summary',
        'scope_entry_kind',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'scope_entry_kind' => ScopeEntryKind::class,
            'usage_count' => 'integer',
        ];
    }

    public function observations(): HasMany
    {
        return $this->hasMany(ScopeEntryConceptObservation::class);
    }

    public function entryKind(): ScopeEntryKind
    {
        $kind = $this->scope_entry_kind;

        return $kind instanceof ScopeEntryKind
            ? $kind
            : ScopeEntryKind::fromStored(is_string($kind) ? $kind : null);
    }

    /** Advisor-facing operational projection (default shop label). */
    public function advisorProjection(): string
    {
        return RepairOrderFreeText::normalize($this->canonical_summary);
    }

    /**
     * @return list<string>
     */
    public function projectionsFor(ScopeLanguageAudience $audience): array
    {
        if ($audience === ScopeLanguageAudience::Advisor) {
            return [$this->advisorProjection()];
        }

        return $this->observations()
            ->where('audience', $audience->value)
            ->orderByDesc('updated_at')
            ->pluck('observed_summary')
            ->map(fn (string $text): string => RepairOrderFreeText::normalize($text))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
