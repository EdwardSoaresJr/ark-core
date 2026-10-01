<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop-learned scope vocabulary: featured match + grouped suggestions.
 * Sources: historical concerns, concepts, and synonym observations.
 */
final class ScopeEntryVocabularyQuery
{
    /**
     * @return array{
     *     featured: array{entry_kind: string, summary: string, concept_id: int|null, usage_count: int}|null,
     *     groups: list<array{
     *         entry_kind: string,
     *         label: string,
     *         suggestions: list<array{summary: string, concept_id: int|null, usage_count: int}>,
     *     }>,
     *     has_matches: bool,
     * }
     */
    public function suggest(string $query, int $perGroup = 4): array
    {
        $needle = trim($query);

        if (mb_strlen($needle) < 2) {
            return [
                'featured' => null,
                'groups' => [],
                'has_matches' => false,
            ];
        }

        $candidates = $this->collectCandidates($needle);

        if ($candidates->isEmpty()) {
            return [
                'featured' => null,
                'groups' => [],
                'has_matches' => false,
            ];
        }

        $featured = $this->pickFeatured($candidates);
        $groups = $this->buildGroups($candidates, $featured, $perGroup);

        return [
            'featured' => $featured,
            'groups' => $groups,
            'has_matches' => true,
        ];
    }

    /**
     * @return Collection<int, array{entry_kind: string, summary: string, concept_id: int|null, usage_count: int}>
     */
    private function collectCandidates(string $needle): Collection
    {
        $like = '%'.addcslashes($needle, '%_').'%';
        $normalizedNeedle = ScopeEntryConceptNormalizer::normalize($needle);
        $merged = collect();

        /** @var Collection<int, object{scope_entry_kind: string, summary: string, usage_count: int}> $fromConcerns */
        $fromConcerns = DB::table('repair_order_concerns')
            ->select([
                'scope_entry_kind',
                'summary',
                DB::raw('COUNT(*) as usage_count'),
            ])
            ->where('summary', 'like', $like)
            ->groupBy('scope_entry_kind', 'summary')
            ->get();

        foreach ($fromConcerns as $row) {
            $this->mergeCandidate($merged, (string) $row->scope_entry_kind, (string) $row->summary, null, (int) $row->usage_count);
        }

        if (Schema::hasTable('scope_entry_concepts')) {
            $fromConcepts = DB::table('scope_entry_concepts')
                ->select([
                    'scope_entry_kind',
                    'canonical_summary as summary',
                    'usage_count',
                    'id as concept_id',
                ])
                ->where('canonical_summary', 'like', $like)
                ->get();

            foreach ($fromConcepts as $row) {
                $this->mergeCandidate(
                    $merged,
                    (string) $row->scope_entry_kind,
                    (string) $row->summary,
                    (int) $row->concept_id,
                    (int) $row->usage_count,
                );
            }

            $aliasLike = $normalizedNeedle !== '' ? '%'.$normalizedNeedle.'%' : $like;

            $fromAliases = DB::table('scope_entry_concept_observations as o')
                ->join('scope_entry_concepts as c', 'c.id', '=', 'o.scope_entry_concept_id')
                ->select([
                    'c.scope_entry_kind',
                    'c.canonical_summary as summary',
                    'o.observed_summary',
                    'c.usage_count',
                    'c.id as concept_id',
                ])
                ->where(function ($query) use ($like, $aliasLike): void {
                    $query->where('o.observed_summary', 'like', $like)
                        ->orWhere('o.normalized_summary', 'like', $aliasLike);
                })
                ->get();

            foreach ($fromAliases as $row) {
                $canonical = (string) $row->summary;
                $displaySummary = $this->displaySummaryForAlias(
                    $canonical,
                    (string) ($row->observed_summary ?? ''),
                    $needle,
                );

                $this->mergeCandidate(
                    $merged,
                    (string) $row->scope_entry_kind,
                    $displaySummary,
                    (int) $row->concept_id,
                    (int) $row->usage_count,
                );
            }
        }

        return $merged->values();
    }

    /**
     * @param  Collection<int, array{entry_kind: string, summary: string, concept_id: int|null, usage_count: int}>  $merged
     */
    private function mergeCandidate(Collection $merged, string $entryKind, string $summary, ?int $conceptId, int $usageCount): void
    {
        $summary = RepairOrderFreeText::normalize($summary);

        if ($summary === '') {
            return;
        }

        $key = $entryKind.'|'.mb_strtolower($summary);
        $existing = $merged->get($key);

        if ($existing === null) {
            $merged->put($key, [
                'entry_kind' => $entryKind,
                'summary' => $summary,
                'concept_id' => $conceptId,
                'usage_count' => $usageCount,
            ]);

            return;
        }

        $merged->put($key, [
            'entry_kind' => $entryKind,
            'summary' => $summary,
            'concept_id' => $conceptId ?? $existing['concept_id'],
            'usage_count' => max($existing['usage_count'], $usageCount),
        ]);
    }

    /**
     * @param  Collection<int, array{entry_kind: string, summary: string, concept_id: int|null, usage_count: int}>  $candidates
     * @return array{entry_kind: string, summary: string, concept_id: int|null, usage_count: int}|null
     */
    private function pickFeatured(Collection $candidates): ?array
    {
        $top = $candidates->sortByDesc('usage_count')->first();

        return is_array($top) ? $top : null;
    }

    /**
     * @param  Collection<int, array{entry_kind: string, summary: string, concept_id: int|null, usage_count: int}>  $candidates
     * @param  array{entry_kind: string, summary: string, concept_id: int|null, usage_count: int}|null  $featured
     * @return list<array{entry_kind: string, label: string, suggestions: list<array{summary: string, concept_id: int|null, usage_count: int}>}>
     */
    private function buildGroups(Collection $candidates, ?array $featured, int $perGroup): array
    {
        $groups = [];

        foreach ($this->groupOrder() as $kind) {
            $kindValue = $kind->value;
            $rows = $candidates
                ->filter(fn (array $row): bool => $row['entry_kind'] === $kindValue)
                ->sortByDesc('usage_count')
                ->values();

            if ($rows->isEmpty()) {
                continue;
            }

            $suggestions = $rows
                ->take($perGroup)
                ->map(fn (array $row): array => [
                    'summary' => $row['summary'],
                    'concept_id' => $row['concept_id'],
                    'usage_count' => $row['usage_count'],
                ])
                ->values()
                ->all();

            $groups[] = [
                'entry_kind' => $kindValue,
                'label' => $kind->vocabularyGroupLabel(),
                'suggestions' => $suggestions,
            ];
        }

        return $groups;
    }

    /**
     * @return list<ScopeEntryKind>
     */
    private function groupOrder(): array
    {
        return [
            ScopeEntryKind::CustomerRequested,
            ScopeEntryKind::CustomerConcern,
            ScopeEntryKind::Diagnostic,
            ScopeEntryKind::WarrantyRecheck,
            ScopeEntryKind::CourtesyInspection,
        ];
    }

    private function displaySummaryForAlias(string $canonical, string $observed, string $needle): string
    {
        $canonical = RepairOrderFreeText::normalize($canonical);
        $observed = RepairOrderFreeText::normalize($observed);

        if ($observed === '') {
            return $canonical;
        }

        if (! ScopeEntrySummaryResolver::collapsedSpecificity($observed, $canonical)) {
            return $canonical;
        }

        if (! $this->needleMatchesObserved($observed, $needle)) {
            return $canonical;
        }

        return $observed;
    }

    private function needleMatchesObserved(string $observed, string $needle): bool
    {
        $observedLower = mb_strtolower(RepairOrderFreeText::normalize($observed));
        $needleLower = mb_strtolower(RepairOrderFreeText::normalize($needle));

        if ($needleLower === '') {
            return true;
        }

        if (str_starts_with($observedLower, $needleLower)) {
            return true;
        }

        return ScopeEntrySummaryResolver::containsWholePhrase($observed, $needle);
    }
}
