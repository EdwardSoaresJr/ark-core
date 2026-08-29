<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Content\GeneratedCommonProblemRepository;

final class CommonProblemRegistry
{
    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     tier: int,
     *     concern_prefill: string,
     *     meta_description: string,
     *     problem: string,
     *     symptoms: list<string>,
     *     can_drive_heading: string,
     *     can_drive: list<string>,
     *     common_causes: list<string>,
     *     what_happens_next: list<string>,
     *     path: string,
     *     often_confused_with?: list<string>,
     *     if_you_ignore?: list<string>,
     *     repair_overview?: list<string>,
     *     faq?: list<array{question: string, answer: string}>,
     *     related_problem_slugs?: list<string>
     * }>
     */
    public static function all(): array
    {
        $configProblems = collect(self::configuredProblems())
            ->map(fn (array $problem): array => self::normalize($problem))
            ->keyBy('slug');

        $generated = collect(app(GeneratedCommonProblemRepository::class)->allForRegistry())
            ->keyBy('slug');

        return $configProblems
            ->merge($generated)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     slug: string,
     *     title: string,
     *     tier: int,
     *     concern_prefill: string,
     *     meta_description: string,
     *     problem: string,
     *     symptoms: list<string>,
     *     can_drive_heading: string,
     *     can_drive: list<string>,
     *     common_causes: list<string>,
     *     what_happens_next: list<string>,
     *     path: string
     * }|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $problem) {
            if ($problem['slug'] === $slug) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return collect(self::all())->pluck('slug')->all();
    }

    /**
     * Homepage symptom chooser — high-intent problems customers self-identify with first.
     *
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     tier: int,
     *     concern_prefill: string,
     *     meta_description: string,
     *     problem: string,
     *     symptoms: list<string>,
     *     can_drive_heading: string,
     *     can_drive: list<string>,
     *     common_causes: list<string>,
     *     what_happens_next: list<string>,
     *     path: string,
     *     often_confused_with?: list<string>,
     *     if_you_ignore?: list<string>,
     *     repair_overview?: list<string>,
     *     faq?: list<array{question: string, answer: string}>,
     *     related_problem_slugs?: list<string>
     * }>
     */
    public static function featuredForHomepage(): array
    {
        $slugs = [
            'check-engine-light',
            'car-wont-start',
            'brake-noise',
            'engine-overheating',
            'battery-keeps-dying',
            'ac-not-cold',
        ];

        return collect($slugs)
            ->map(fn (string $slug): ?array => self::find($slug))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Common-problems index — symptom list customers scan first.
     * Keeps DTC / long-tail pages indexable without dumping every code on the index.
     *
     * @return list<array<string, mixed>>
     */
    public static function featuredForIndexSymptoms(): array
    {
        $slugs = [
            'check-engine-light',
            'car-wont-start',
            'brake-noise',
            'engine-overheating',
            'ac-not-cold',
            'battery-keeps-dying',
            'wheel-bearing-noise',
            'suspension-noise',
        ];

        return collect($slugs)
            ->map(fn (string $slug): ?array => self::find($slug))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Local service / transactional intent pages — homepage discovery, separate from symptoms.
     *
     * @return list<array<string, mixed>>
     */
    public static function featuredLocalServices(): array
    {
        $slugs = [
            'auto-repair-demo-city',
            'mechanic-demo-city',
            'car-diagnostics-demo-city',
            'brake-repair-demo-city',
            'tune-up-demo-city',
            'audi-repair-demo-city',
        ];

        return collect($slugs)
            ->map(fn (string $slug): ?array => self::find($slug))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Common-problems index — local services (slightly longer than homepage featured).
     *
     * @return list<array<string, mixed>>
     */
    public static function featuredForIndexLocalServices(): array
    {
        $slugs = [
            'auto-repair-demo-city',
            'mechanic-demo-city',
            'car-diagnostics-demo-city',
            'brake-repair-demo-city',
            'tune-up-demo-city',
            'audi-repair-demo-city',
            'car-fluid-service',
            'electrical-diagnostics',
        ];

        return collect($slugs)
            ->map(fn (string $slug): ?array => self::find($slug))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Compact catalog for live client-side search (title + slug + optional teaser).
     *
     * @return list<array{slug: string, title: string, teaser: string, href: string}>
     */
    public static function searchCatalog(): array
    {
        return collect(self::all())
            ->sortBy('title')
            ->map(fn (array $problem): array => [
                'slug' => $problem['slug'],
                'title' => $problem['title'],
                'teaser' => (string) ($problem['card_teaser'] ?? ''),
                'href' => route('public.common-problems.show', $problem['slug']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function transactionalSlugs(): array
    {
        return [
            'auto-repair-demo-city',
            'mechanic-demo-city',
            'car-diagnostics-demo-city',
            'brake-repair-demo-city',
            'tune-up-demo-city',
            'audi-repair-demo-city',
            'car-fluid-service',
            'brake-fluid-service',
            'transmission-fluid-change',
            'electrical-diagnostics',
            'burnt-transmission-fluid',
            'misfire-under-load',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function localServicePages(): array
    {
        $transactional = self::transactionalSlugs();

        return collect(self::all())
            ->filter(fn (array $problem): bool => in_array($problem['slug'], $transactional, true))
            ->sortBy([
                ['tier', 'asc'],
                ['title', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function symptomPages(): array
    {
        $transactional = self::transactionalSlugs();

        return collect(self::all())
            ->reject(fn (array $problem): bool => in_array($problem['slug'], $transactional, true))
            ->sortBy([
                ['tier', 'asc'],
                ['title', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function configuredProblems(): array
    {
        return array_merge(
            config('common_problems', []),
            config('common_problem_dtc_codes', []),
        );
    }

    /**
     * @param  array<string, mixed>  $problem
     * @return array{
     *     slug: string,
     *     title: string,
     *     tier: int,
     *     concern_prefill: string,
     *     meta_description: string,
     *     problem: string,
     *     symptoms: list<string>,
     *     can_drive_heading: string,
     *     can_drive: list<string>,
     *     common_causes: list<string>,
     *     what_happens_next: list<string>,
     *     path: string
     * }
     */
    private static function normalize(array $problem): array
    {
        $slug = (string) ($problem['slug'] ?? '');

        return [
            'slug' => $slug,
            'title' => (string) ($problem['title'] ?? ''),
            'page_title' => (string) ($problem['page_title'] ?? $problem['title'] ?? ''),
            'seo_title' => (string) ($problem['seo_title'] ?? ''),
            'tier' => (int) ($problem['tier'] ?? 2),
            'concern_prefill' => (string) ($problem['concern_prefill'] ?? ''),
            'card_teaser' => (string) ($problem['card_teaser'] ?? ''),
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
            'diagnostic_process' => array_values(array_map('strval', $problem['diagnostic_process'] ?? [])),
            'typical_repairs' => array_values(array_map('strval', $problem['typical_repairs'] ?? [])),
            'repair_overview' => array_values(array_map('strval', $problem['repair_overview'] ?? [])),
            'shop_experience' => is_array($problem['shop_experience'] ?? null) ? $problem['shop_experience'] : [],
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
        ];
    }
}
