<?php

namespace App\Ark\Growth\Content;

use App\Ark\Growth\Models\GrowthGeneratedCommonProblem;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class GeneratedCommonProblemRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function allForRegistry(): array
    {
        if (! Schema::hasTable('growth_generated_common_problems')) {
            return [];
        }

        return GrowthGeneratedCommonProblem::query()
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (GrowthGeneratedCommonProblem $row): array => $this->normalizeProblem($row))
            ->all();
    }

    public function findBySlug(string $slug): ?array
    {
        if (! Schema::hasTable('growth_generated_common_problems')) {
            return null;
        }

        $row = GrowthGeneratedCommonProblem::query()->where('slug', $slug)->first();

        return $row !== null ? $this->normalizeProblem($row) : null;
    }

    public function storeFromOpportunity(GrowthOpportunity $opportunity, array $problem): GrowthGeneratedCommonProblem
    {
        if (! Schema::hasTable('growth_generated_common_problems')) {
            throw new \RuntimeException('growth_generated_common_problems table is not migrated.');
        }

        $slug = (string) ($problem['slug'] ?? '');

        return GrowthGeneratedCommonProblem::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'growth_opportunity_id' => $opportunity->id,
                'problem' => $problem,
                'published_at' => now(),
            ],
        );
    }

    /**
     * @return Collection<int, GrowthGeneratedCommonProblem>
     */
    public function publishedSince(\DateTimeInterface $since): Collection
    {
        if (! Schema::hasTable('growth_generated_common_problems')) {
            return collect();
        }

        return GrowthGeneratedCommonProblem::query()
            ->where('published_at', '>=', $since)
            ->orderByDesc('published_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProblem(GrowthGeneratedCommonProblem $row): array
    {
        $problem = is_array($row->problem) ? $row->problem : [];
        $slug = (string) ($problem['slug'] ?? $row->slug);

        return [
            'slug' => $slug,
            'title' => (string) ($problem['title'] ?? ''),
            'page_title' => (string) ($problem['page_title'] ?? $problem['title'] ?? ''),
            'tier' => (int) ($problem['tier'] ?? 2),
            'concern_prefill' => (string) ($problem['concern_prefill'] ?? ''),
            'meta_description' => (string) ($problem['meta_description'] ?? ''),
            'problem' => (string) ($problem['problem'] ?? ''),
            'symptoms' => array_values(array_map('strval', $problem['symptoms'] ?? [])),
            'can_drive_heading' => (string) ($problem['can_drive_heading'] ?? 'Can I keep driving?'),
            'can_drive' => array_values(array_map('strval', $problem['can_drive'] ?? [])),
            'common_causes' => array_values(array_map('strval', $problem['common_causes'] ?? [])),
            'what_happens_next' => array_values(array_map('strval', $problem['what_happens_next'] ?? [])),
            'path' => '/common-problems/'.$slug,
            'often_confused_with' => array_values(array_map('strval', $problem['often_confused_with'] ?? [])),
            'if_you_ignore' => array_values(array_map('strval', $problem['if_you_ignore'] ?? [])),
            'repair_overview' => array_values(array_map('strval', $problem['repair_overview'] ?? [])),
            'faq' => array_values(array_map(
                static fn (array $item): array => [
                    'question' => (string) ($item['question'] ?? ''),
                    'answer' => (string) ($item['answer'] ?? ''),
                ],
                is_array($problem['faq'] ?? null) ? $problem['faq'] : [],
            )),
            'related_problem_slugs' => array_values(array_map('strval', $problem['related_problem_slugs'] ?? [])),
            'featured_media' => CommonProblemFeaturedMedia::rawForSlug(
                $slug,
                is_array($problem['featured_media'] ?? null) ? $problem['featured_media'] : null,
            ),
            'generated' => true,
        ];
    }
}
