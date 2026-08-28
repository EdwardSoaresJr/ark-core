<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Packages one common-problem page as a problem authority — static shop knowledge
 * today, shop-verified repair outcomes when the resolver earns them.
 */
final class CommonProblemAuthorityProjection
{
    public function __construct(
        private readonly CommonProblemShopExperienceResolver $shopExperience,
    ) {}

    /**
     * @param  array<string, mixed>  $problem
     * @return array{
     *     slug: string,
     *     title: string,
     *     page_title: string,
     *     dtc_code: string|null,
     *     plain_english_meaning: string|null,
     *     problem: string,
     *     symptoms: list<string>,
     *     can_drive_heading: string,
     *     can_drive_is_safety: bool,
     *     can_drive: list<string>,
     *     common_causes: list<string>,
     *     often_confused_with: list<string>,
     *     often_confused_heading: string,
     *     if_you_ignore: list<string>,
     *     diagnostic_process: list<string>,
     *     typical_repairs: list<string>,
     *     what_happens_next: list<string>,
     *     faq: list<array{question: string, answer: string}>,
     *     related_problems: list<array{slug: string, title: string, href: string}>,
     *     shop_experience: array{
     *         verified_repair_count: int,
     *         most_common_fix: string|null,
     *         last_updated_label: string|null,
     *         average_diagnostic_time_label: string|null,
     *         repairs: list<array{vehicle: string, summary: string, outcome: string|null}>,
     *         has_signals: bool
     *     }
     * }
     */
    public function forProblem(array $problem): array
    {
        $slug = (string) ($problem['slug'] ?? '');
        $title = (string) ($problem['title'] ?? '');
        $pageTitle = (string) ($problem['page_title'] ?? $title);
        $dtcCode = self::dtcCode($slug, $title);
        $relatedProblems = collect($problem['related_problem_slugs'] ?? [])
            ->map(function (string $relatedSlug): ?array {
                $related = CommonProblemRegistry::find($relatedSlug);

                if ($related === null) {
                    return null;
                }

                return [
                    'slug' => $related['slug'],
                    'title' => $related['title'],
                    'href' => route('public.common-problems.show', $related['slug']),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $shopExperience = $this->resolveShopExperience($slug, $problem);

        return [
            'slug' => $slug,
            'title' => $title,
            'page_title' => $pageTitle,
            'dtc_code' => $dtcCode,
            'plain_english_meaning' => self::plainEnglishMeaning($pageTitle, $dtcCode),
            'problem' => (string) ($problem['problem'] ?? ''),
            'symptoms' => array_values(array_map('strval', $problem['symptoms'] ?? [])),
            'can_drive_heading' => (string) ($problem['can_drive_heading'] ?? 'Can I keep driving?'),
            'can_drive_is_safety' => self::canDriveIsSafetyHeading(
                (string) ($problem['can_drive_heading'] ?? 'Can I keep driving?'),
            ),
            'can_drive' => array_values(array_map('strval', $problem['can_drive'] ?? [])),
            'common_causes' => array_values(array_map('strval', $problem['common_causes'] ?? [])),
            'often_confused_with' => array_values(array_map('strval', $problem['often_confused_with'] ?? [])),
            'often_confused_heading' => $this->oftenConfusedHeading($slug, $problem),
            'if_you_ignore' => array_values(array_map('strval', $problem['if_you_ignore'] ?? [])),
            'diagnostic_process' => $this->diagnosticProcess($problem),
            'typical_repairs' => $this->typicalRepairs($problem),
            'what_happens_next' => array_values(array_map('strval', $problem['what_happens_next'] ?? [])),
            'faq' => array_values(array_map(
                static fn (array $item): array => [
                    'question' => (string) ($item['question'] ?? ''),
                    'answer' => (string) ($item['answer'] ?? ''),
                ],
                is_array($problem['faq'] ?? null) ? $problem['faq'] : [],
            )),
            'related_problems' => $relatedProblems,
            'shop_experience' => $shopExperience,
            'featured_media' => CommonProblemFeaturedMedia::featuredForDisplay(
                is_array($problem['featured_media'] ?? null) ? $problem['featured_media'] : null,
            ),
            'featured_media_gallery' => CommonProblemFeaturedMedia::galleryForDisplay(
                is_array($problem['featured_media'] ?? null) ? $problem['featured_media'] : null,
            ),
        ];
    }

    /**
     * Amber drive-safety callout is only for true driveability headings.
     * Transactional pages reuse can_drive_* for "what's included" / shop reasons —
     * those stay ordinary reference sections.
     */
    private static function canDriveIsSafetyHeading(string $heading): bool
    {
        return preg_match(
            '/\b(can i keep|is it safe to|keep trying|keep driving|safe to drive)\b/i',
            $heading,
        ) === 1;
    }

    private static function dtcCode(string $slug, string $title): ?string
    {
        if (preg_match('/^p\d{4}[a-z]?$/i', $slug) === 1) {
            return strtoupper($slug);
        }

        if (preg_match('/\b(P\d{4}[A-Z]?)\b/', $title, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function plainEnglishMeaning(string $pageTitle, ?string $dtcCode): ?string
    {
        if ($dtcCode === null || trim($pageTitle) === '') {
            return null;
        }

        $stripped = preg_replace(
            '/^'.preg_quote($dtcCode, '/').'(?:\s+Code)?\s*:\s*/i',
            '',
            $pageTitle,
        );

        $stripped = trim((string) $stripped);

        if ($stripped === '' || strcasecmp($stripped, $pageTitle) === 0) {
            return null;
        }

        return $stripped;
    }

    /**
     * @param  array<string, mixed>  $problem
     */
    private function oftenConfusedHeading(string $slug, array $problem): string
    {
        if (filled($problem['often_confused_heading'] ?? null)) {
            return (string) $problem['often_confused_heading'];
        }

        if (in_array($slug, CommonProblemRegistry::transactionalSlugs(), true)) {
            return 'Common misconceptions';
        }

        return 'Often sounds like';
    }

    /**
     * @param  array<string, mixed>  $problem
     * @return list<string>
     */
    private function diagnosticProcess(array $problem): array
    {
        $explicit = array_values(array_map('strval', $problem['diagnostic_process'] ?? []));

        if ($explicit !== []) {
            return $explicit;
        }

        return array_values(array_map('strval', $problem['repair_overview'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $problem
     * @return list<string>
     */
    private function typicalRepairs(array $problem): array
    {
        $explicit = array_values(array_map('strval', $problem['typical_repairs'] ?? []));

        if ($explicit !== []) {
            return $explicit;
        }

        return array_values(array_map('strval', $problem['repair_overview'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $problem
     * @return array{
     *     verified_repair_count: int,
     *     most_common_fix: string|null,
     *     last_updated_label: string|null,
     *     average_diagnostic_time_label: string|null,
     *     repairs: list<array{vehicle: string, summary: string, outcome: string|null}>,
     *     has_signals: bool
     * }
     */
    private function resolveShopExperience(string $slug, array $problem): array
    {
        $resolved = $this->shopExperience->forSlug($slug);
        $configured = is_array($problem['shop_experience'] ?? null) ? $problem['shop_experience'] : [];

        $verifiedRepairCount = max(
            (int) ($resolved['verified_repair_count'] ?? 0),
            (int) ($configured['verified_repair_count'] ?? 0),
        );

        $repairs = $this->normalizeRepairs($configured['repairs'] ?? $resolved['repairs'] ?? []);

        $mostCommonFix = self::nonEmptyString($configured['most_common_fix'] ?? null)
            ?? self::nonEmptyString($resolved['most_common_fix'] ?? null);

        $lastUpdatedLabel = self::nonEmptyString($configured['last_updated_label'] ?? null)
            ?? self::nonEmptyString($resolved['last_updated_label'] ?? null);

        $averageDiagnosticTimeLabel = self::nonEmptyString($configured['average_diagnostic_time_label'] ?? null)
            ?? self::nonEmptyString($resolved['average_diagnostic_time_label'] ?? null);

        $hasSignals = $verifiedRepairCount > 0
            || $mostCommonFix !== null
            || $lastUpdatedLabel !== null
            || $averageDiagnosticTimeLabel !== null
            || $repairs !== [];

        return [
            'verified_repair_count' => $verifiedRepairCount,
            'most_common_fix' => $mostCommonFix,
            'last_updated_label' => $lastUpdatedLabel,
            'average_diagnostic_time_label' => $averageDiagnosticTimeLabel,
            'repairs' => $repairs,
            'has_signals' => $hasSignals,
        ];
    }

    /**
     * @return list<array{vehicle: string, summary: string, outcome: string|null}>
     */
    private function normalizeRepairs(mixed $repairs): array
    {
        if (! is_array($repairs)) {
            return [];
        }

        return collect($repairs)
            ->map(function (mixed $repair): ?array {
                if (! is_array($repair)) {
                    return null;
                }

                $summary = trim((string) ($repair['summary'] ?? ''));

                if ($summary === '') {
                    return null;
                }

                return [
                    'vehicle' => trim((string) ($repair['vehicle'] ?? 'Customer vehicle')),
                    'summary' => $summary,
                    'outcome' => self::nonEmptyString($repair['outcome'] ?? null),
                ];
            })
            ->filter()
            ->take(5)
            ->values()
            ->all();
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
