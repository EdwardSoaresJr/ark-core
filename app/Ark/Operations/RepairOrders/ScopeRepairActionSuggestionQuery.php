<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Facades\DB;

/**
 * Shop-learned repair action titles for a scope concept — shortcuts, not committed lines.
 */
final class ScopeRepairActionSuggestionQuery
{
    /**
     * @return list<string>
     */
    public function forConcern(RepairOrderConcern $concern): array
    {
        $learned = $this->learnedTitles($concern);

        if ($learned !== []) {
            return $learned;
        }

        return $this->starterTitles($concern->summary, $concern->entryKind());
    }

    /**
     * @return list<string>
     */
    private function learnedTitles(RepairOrderConcern $concern): array
    {
        $query = DB::table('repair_order_work_groups as wg')
            ->join('repair_order_concerns as c', 'c.id', '=', 'wg.repair_order_concern_id')
            ->select('wg.title', DB::raw('COUNT(*) as usage_count'))
            ->whereNotNull('wg.title')
            ->where('wg.title', '!=', '');

        if ($concern->scope_entry_concept_id !== null) {
            $query->where('c.scope_entry_concept_id', $concern->scope_entry_concept_id);
        } else {
            $query->where('c.summary', $concern->summary);
        }

        return $query
            ->groupBy('wg.title')
            ->orderByDesc('usage_count')
            ->orderBy('wg.title')
            ->limit(5)
            ->pluck('title')
            ->map(fn ($title): string => RepairOrderFreeText::normalize((string) $title))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function starterTitles(string $summary, ScopeEntryKind $entryKind): array
    {
        $normalized = mb_strtolower(RepairOrderFreeText::normalize($summary));

        if (str_contains($normalized, 'brake')) {
            if (str_contains($normalized, 'front')) {
                return [
                    'Front Brake Pad Replacement',
                    'Front Brake Pad & Rotor Replacement',
                    'Brake Inspection',
                ];
            }

            if (str_contains($normalized, 'rear')) {
                return [
                    'Rear Brake Pad Replacement',
                    'Rear Brake Pad & Rotor Replacement',
                    'Brake Inspection',
                ];
            }

            return [
                'Brake Pad Replacement',
                'Brake Pad & Rotor Replacement',
                'Brake Inspection',
            ];
        }

        if (str_contains($normalized, 'oil change')) {
            return [
                'Engine Oil & Filter Change',
                'Multi-Point Inspection',
            ];
        }

        if ($entryKind === ScopeEntryKind::CustomerConcern && str_contains($normalized, 'leak')) {
            return [
                'Fluid Leak Diagnosis',
                'Pressure Test',
            ];
        }

        if ($entryKind === ScopeEntryKind::Diagnostic) {
            return [
                'Diagnostic Inspection',
                'Road Test',
            ];
        }

        return [];
    }
}
