<?php

namespace App\Ark\Growth\Opportunities;

use App\Ark\Growth\Content\ContentBuilderSchema;
use App\Ark\Growth\Models\GrowthOpportunity;

final class OpportunityAcceptanceEvaluator
{
    /**
     * @param  list<array{key: string, label: string, kind: string, required: bool, satisfied: bool}>  $criteria
     * @return list<array{key: string, label: string, kind: string, required: bool, satisfied: bool}>
     */
    public function evaluate(GrowthOpportunity $opportunity, array $criteria): array
    {
        $draft = ContentBuilderSchema::normalize(
            $opportunity->content_draft,
            $opportunity->title,
            $opportunity->search_query,
        );

        return collect($criteria)
            ->map(function (array $item) use ($draft, $opportunity): array {
                if ($item['kind'] !== 'builder') {
                    return $item;
                }

                $satisfied = match ($item['key']) {
                    'faq_included' => count($draft['faq']) > 0,
                    'internal_links_added' => count($draft['related_services']) > 0
                        || count($draft['related_problems']) > 0
                        || count($draft['related_vehicles']) > 0,
                    'breadcrumbs_present' => ContentBuilderSchema::previewPath($draft) !== null,
                    'canonical_valid' => ContentBuilderSchema::previewPath($draft) !== null
                        && filled($draft['title']),
                    'title_meta_rewritten' => filled($draft['title']) && filled($draft['summary']),
                    default => (bool) ($item['satisfied'] ?? false),
                };

                return [...$item, 'satisfied' => $satisfied];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{key: string, label: string, kind: string, required: bool, satisfied: bool}>  $criteria
     */
    public function allRequiredSatisfied(array $criteria): bool
    {
        return $this->gateSatisfied($criteria, 'publish');
    }

    /**
     * @param  list<array{key: string, label: string, kind: string, required: bool, satisfied: bool, gate?: string}>  $criteria
     */
    public function gateSatisfied(array $criteria, string $gate): bool
    {
        return collect($criteria)
            ->filter(static fn (array $item): bool => (bool) $item['required'] && ($item['gate'] ?? 'publish') === $gate)
            ->every(static fn (array $item): bool => (bool) $item['satisfied']);
    }

    /**
     * @param  list<array{key: string, label: string, kind: string, required: bool, satisfied: bool, gate?: string}>  $criteria
     * @return array{satisfied: int, required: int, total: int}
     */
    public function progressForGate(array $criteria, string $gate): array
    {
        $required = collect($criteria)
            ->filter(static fn (array $item): bool => (bool) $item['required'] && ($item['gate'] ?? 'publish') === $gate);
        $satisfiedRequired = $required->where('satisfied', true);

        return [
            'satisfied' => $satisfiedRequired->count(),
            'required' => $required->count(),
            'total' => count($criteria),
        ];
    }

    /**
     * @param  list<array{key: string, label: string, kind: string, required: bool, satisfied: bool}>  $criteria
     * @return array{satisfied: int, required: int, total: int}
     */
    public function progress(array $criteria): array
    {
        $required = collect($criteria)->where('required', true);
        $satisfiedRequired = $required->where('satisfied', true);

        return [
            'satisfied' => $satisfiedRequired->count(),
            'required' => $required->count(),
            'total' => count($criteria),
        ];
    }
}
