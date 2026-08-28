<?php

namespace App\Ark\Growth\Opportunities;

final class OpportunityAcceptanceCriteriaTemplate
{
    /**
     * @return list<array{key: string, label: string, kind: string, required: bool, gate: string}>
     */
    public static function forAction(GrowthOpportunityAction $action): array
    {
        return match ($action) {
            GrowthOpportunityAction::Create => self::createPage(),
            GrowthOpportunityAction::Improve => self::improvePage(),
            default => self::improvePage(),
        };
    }

    /**
     * @return list<array{key: string, label: string, kind: string, required: bool, gate: string}>
     */
    private static function createPage(): array
    {
        return [
            ['key' => 'faq_included', 'label' => 'FAQ included', 'kind' => 'builder', 'required' => true, 'gate' => 'publish'],
            ['key' => 'internal_links_added', 'label' => 'Internal links added', 'kind' => 'builder', 'required' => true, 'gate' => 'publish'],
            ['key' => 'breadcrumbs_present', 'label' => 'Breadcrumbs present', 'kind' => 'builder', 'required' => true, 'gate' => 'publish'],
            ['key' => 'canonical_valid', 'label' => 'Canonical valid', 'kind' => 'builder', 'required' => true, 'gate' => 'publish'],
            ['key' => 'page_published', 'label' => 'Page published', 'kind' => 'manual', 'required' => true, 'gate' => 'measuring'],
            ['key' => 'json_ld_valid', 'label' => 'JSON-LD valid', 'kind' => 'manual', 'required' => true, 'gate' => 'measuring'],
            ['key' => 'mobile_score_90', 'label' => 'Mobile score > 90', 'kind' => 'manual', 'required' => true, 'gate' => 'measuring'],
            ['key' => 'indexed_by_google', 'label' => 'Indexed by Google', 'kind' => 'manual', 'required' => true, 'gate' => 'measuring'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, kind: string, required: bool, gate: string}>
     */
    private static function improvePage(): array
    {
        return [
            ['key' => 'title_meta_rewritten', 'label' => 'Title and meta rewritten', 'kind' => 'builder', 'required' => true, 'gate' => 'publish'],
            ['key' => 'faq_included', 'label' => 'FAQ included or expanded', 'kind' => 'builder', 'required' => true, 'gate' => 'publish'],
            ['key' => 'internal_links_added', 'label' => 'Internal links added', 'kind' => 'builder', 'required' => true, 'gate' => 'publish'],
            ['key' => 'canonical_valid', 'label' => 'Canonical valid', 'kind' => 'builder', 'required' => true, 'gate' => 'publish'],
            ['key' => 'page_published', 'label' => 'Changes published', 'kind' => 'manual', 'required' => true, 'gate' => 'measuring'],
            ['key' => 'json_ld_valid', 'label' => 'JSON-LD valid', 'kind' => 'manual', 'required' => true, 'gate' => 'measuring'],
        ];
    }

    /**
     * @param  list<array{key: string, label: string, kind: string, required: bool, gate?: string, satisfied?: bool}>|null  $existing
     * @return list<array{key: string, label: string, kind: string, required: bool, gate: string, satisfied: bool}>
     */
    public static function hydrate(GrowthOpportunityAction $action, ?array $existing): array
    {
        $existingByKey = collect($existing ?? [])->keyBy('key');

        return collect(self::forAction($action))
            ->map(function (array $item) use ($existingByKey): array {
                $prior = $existingByKey->get($item['key']);

                return [
                    ...$item,
                    'gate' => (string) ($prior['gate'] ?? $item['gate'] ?? 'publish'),
                    'satisfied' => (bool) ($prior['satisfied'] ?? false),
                ];
            })
            ->values()
            ->all();
    }
}
