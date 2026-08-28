<?php

namespace App\Ark\Growth\Content;

use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Str;

/**
 * Pre-fills opportunity content from search query + nearest Common Problem template.
 * Operators review and publish — they should not type empty checklists from scratch.
 */
final class OpportunityContentDraftSeeder
{
    /** @var list<string> */
    private const LOCATION_STOPWORDS = [
        'colorado', 'springs', 'near', 'me', 'shop', 'shops', 'garage', 'auto', 'automotive',
    ];

    /**
     * @return array<string, mixed>
     */
    public function seed(?string $searchQuery, ?string $title = null): array
    {
        $query = trim((string) $searchQuery);
        $label = $this->pageTitle($title, $query);
        $slug = Str::slug($query);
        $shop = ShopSettings::current();
        $shopName = trim((string) ($shop->shop_name ?? '')) ?: 'LugsNPlugs';
        $city = trim((string) ($shop->city ?? '')) ?: 'Colorado Springs';

        $problem = $this->matchRegistryProblem($query);

        if ($problem !== null) {
            return ContentBuilderSchema::normalize(
                $this->fromRegistry($problem, $label, $slug, $shopName, $city, $query),
                $label,
                $query,
            );
        }

        return ContentBuilderSchema::normalize(
            $this->fromQuery($query, $label, $slug, $shopName, $city),
            $label,
            $query,
        );
    }

    private function pageTitle(?string $title, string $query): string
    {
        $label = trim((string) ($title ?? ''));
        $label = ContentBuilderSchema::stripActionPrefix($label);

        if ($label !== '') {
            return $label;
        }

        return Str::title($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromRegistry(
        array $problem,
        string $label,
        string $slug,
        string $shopName,
        string $city,
        string $query,
    ): array {
        $summary = trim((string) ($problem['meta_description'] ?? $problem['problem'] ?? ''));
        if (! str_contains(strtolower($summary), strtolower($city))) {
            $summary = rtrim($summary, '.')." in {$city}.";
        }

        $diagnosis = collect($problem['what_happens_next'] ?? [])
            ->map(static fn (string $line): string => trim($line))
            ->filter()
            ->implode(' ');

        $whenNotToDrive = collect($problem['can_drive'] ?? [])
            ->map(static fn (string $line): string => trim($line))
            ->filter(static fn (string $line): bool => preg_match('/stop|minimal|emergency|not safe|do not|don\'t/i', $line) === 1)
            ->values()
            ->all();

        if ($whenNotToDrive === []) {
            $whenNotToDrive = [
                'Unusual smells, smoke, or warning lights with the symptom',
                'Sudden loss of power, steering, or braking',
                'Noise or vibration that appeared suddenly and is getting worse',
            ];
        }

        $faq = $problem['faq'] ?? [];
        if ($faq === []) {
            $faq = $this->defaultFaq($label, $shopName, $city, $query);
        }

        $relatedProblems = collect($problem['related_problem_slugs'] ?? [])
            ->map(static fn (string $related): string => trim($related))
            ->filter()
            ->values()
            ->all();

        return [
            'title' => $label,
            'slug' => $slug,
            'summary' => $summary,
            'symptoms' => $problem['symptoms'] ?? [],
            'diagnosis' => $diagnosis !== '' ? $diagnosis : "At {$shopName}, we start with a road test when safe, then inspect the systems tied to your concern before recommending parts.",
            'common_causes' => $problem['common_causes'] ?? [],
            'when_not_to_drive' => $whenNotToDrive,
            'faq' => $faq,
            'cta' => 'Talk to a service advisor',
            'related_services' => $this->relatedServicesForQuery($query),
            'related_problems' => $relatedProblems,
            'related_vehicles' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromQuery(string $query, string $label, string $slug, string $shopName, string $city): array
    {
        return [
            'title' => $label,
            'slug' => $slug,
            'summary' => "{$label} in {$city}? {$shopName} explains common symptoms, what to check first, and when to stop driving — before you spend money on guesses.",
            'symptoms' => [
                "Concern matches \"{$query}\"",
                'Symptom started recently or is getting worse',
                'Warning light, noise, smell, or performance change noticed while driving',
            ],
            'diagnosis' => "We confirm the symptom on a road test when safe, inspect the related systems, and show you what we find before recommending repairs at {$shopName}.",
            'common_causes' => [
                'Wear items due for service',
                'Fluid leaks or low levels',
                'Sensor or circuit faults',
                'Recent repair or impact-related damage',
            ],
            'when_not_to_drive' => [
                'Smoke, fuel smell, or active warning lights with the symptom',
                'Sudden loss of power, steering, or braking',
                'Grinding metal sounds or severe vibration',
            ],
            'faq' => $this->defaultFaq($label, $shopName, $city, $query),
            'cta' => 'Talk to a service advisor',
            'related_services' => $this->relatedServicesForQuery($query),
            'related_problems' => [],
            'related_vehicles' => [],
        ];
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private function defaultFaq(string $label, string $shopName, string $city, string $query): array
    {
        return [
            [
                'question' => "Can I keep driving with {$label}?",
                'answer' => "It depends on the symptom. If you have warning lights, smoke, loss of power, or brake/steering issues, drive minimally and call {$shopName}. We'll help you sort urgency before you book.",
            ],
            [
                'question' => "How much does {$label} cost in {$city}?",
                'answer' => "Cost follows diagnosis — {$shopName} inspects first, shows you findings, and quotes approved work. Mention \"{$query}\" when you reach out so we route you to the right advisor.",
            ],
            [
                'question' => 'Do I need an appointment?',
                'answer' => "Same-day openings vary. Text or call {$shopName} with your vehicle and concern — we'll tell you the fastest path in.",
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function relatedServicesForQuery(string $query): array
    {
        $lower = strtolower($query);
        $services = [];

        if (str_contains($lower, 'brake')) {
            $services[] = 'Brake inspection';
        }
        if (str_contains($lower, 'engine') || str_contains($lower, 'overheat') || preg_match('/p0\d+/i', $lower)) {
            $services[] = 'Check engine light diagnosis';
        }
        if (str_contains($lower, 'battery') || str_contains($lower, 'start')) {
            $services[] = 'Starting and charging test';
        }
        if (str_contains($lower, 'ac ') || str_contains($lower, 'a/c') || str_contains($lower, 'air conditioning')) {
            $services[] = 'A/C performance check';
        }
        if ($services === []) {
            $services[] = 'Diagnostic inspection';
        }

        return $services;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function matchRegistryProblem(string $query): ?array
    {
        $queryTokens = $this->topicTokens($query);
        if ($queryTokens === []) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach (CommonProblemRegistry::all() as $problem) {
            $slugTokens = $this->topicTokens(str_replace('-', ' ', (string) $problem['slug']));
            $titleTokens = $this->topicTokens((string) $problem['title']);
            $pool = array_unique([...$slugTokens, ...$titleTokens]);
            $overlap = count(array_intersect($queryTokens, $pool));

            if ($overlap > $bestScore) {
                $bestScore = $overlap;
                $best = $problem;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    /**
     * @return list<string>
     */
    private function topicTokens(string $value): array
    {
        $tokens = preg_split('/[\s\-\/]+/', strtolower($value)) ?: [];

        return collect($tokens)
            ->map(static fn (string $token): string => trim($token))
            ->filter()
            ->reject(fn (string $token): bool => in_array($token, self::LOCATION_STOPWORDS, true))
            ->reject(fn (string $token): bool => strlen($token) < 3)
            ->values()
            ->all();
    }
}
