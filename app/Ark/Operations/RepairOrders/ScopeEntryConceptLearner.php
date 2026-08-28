<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Records observed language → operational meaning relationships.
 *
 * Translation validation (meaning preservation, not fluency):
 *   Recognition — did ARK understand the words?
 *   Translation — did ARK preserve the meaning between dialects?
 *   Resolution  — did the work confirm the meaning? (future)
 */
final class ScopeEntryConceptLearner
{
    public function record(
        RepairOrderConcern $concern,
        ScopeEntryKind $entryKind,
        string $advisorProjection,
        string $observedLanguage,
        ?int $conceptId = null,
    ): ScopeEntryConcept {
        // Vocabulary concepts stay short headlines; concern.summary may be longer customer language.
        $advisorProjection = $this->vocabularyPhrase($advisorProjection);
        $observedLanguage = $this->vocabularyPhrase($observedLanguage);

        $concept = $this->resolveConcept($entryKind, $advisorProjection, $conceptId);
        $concept->increment('usage_count');

        $concern->forceFill(['scope_entry_concept_id' => $concept->id])->save();

        $this->recordProjection(
            $concept,
            $observedLanguage,
            ScopeLanguageAudience::Customer,
            $concern,
        );

        if ($observedLanguage !== $advisorProjection) {
            $this->recordProjection(
                $concept,
                $advisorProjection,
                ScopeLanguageAudience::Advisor,
                $concern,
            );
        }

        return $concept;
    }

    private function resolveConcept(ScopeEntryKind $entryKind, string $advisorProjection, ?int $conceptId): ScopeEntryConcept
    {
        if ($conceptId !== null) {
            $existing = ScopeEntryConcept::query()->find($conceptId);

            if ($existing !== null) {
                return $existing;
            }
        }

        $aliasMatch = $this->findByAlias($advisorProjection, $entryKind);

        if ($aliasMatch !== null) {
            return $aliasMatch;
        }

        return ScopeEntryConcept::query()->firstOrCreate(
            [
                'canonical_summary' => $advisorProjection,
                'scope_entry_kind' => $entryKind->value,
            ],
            [
                'usage_count' => 0,
            ],
        );
    }

    private function vocabularyPhrase(string $text): string
    {
        return mb_substr(RepairOrderFreeText::normalize($text), 0, 255);
    }

    private function findByAlias(string $text, ScopeEntryKind $entryKind): ?ScopeEntryConcept
    {
        $normalized = ScopeEntryConceptNormalizer::normalize($text);

        if ($normalized === '') {
            return null;
        }

        $observation = ScopeEntryConceptObservation::query()
            ->where('normalized_summary', $normalized)
            ->whereHas('concept', fn ($query) => $query->where('scope_entry_kind', $entryKind->value))
            ->with('concept')
            ->first();

        if ($observation !== null) {
            return $observation->concept;
        }

        return ScopeEntryConcept::query()
            ->where('scope_entry_kind', $entryKind->value)
            ->whereRaw('LOWER(canonical_summary) = ?', [mb_strtolower(RepairOrderFreeText::normalize($text))])
            ->first();
    }

    private function recordProjection(
        ScopeEntryConcept $concept,
        string $text,
        ScopeLanguageAudience $audience,
        RepairOrderConcern $concern,
    ): void {
        $normalized = ScopeEntryConceptNormalizer::normalize($text);

        if ($normalized === '') {
            return;
        }

        ScopeEntryConceptObservation::query()->firstOrCreate(
            [
                'scope_entry_concept_id' => $concept->id,
                'normalized_summary' => $normalized,
                'audience' => $audience->value,
            ],
            [
                'observed_summary' => $text,
                'repair_order_concern_id' => $concern->id,
            ],
        );
    }
}
