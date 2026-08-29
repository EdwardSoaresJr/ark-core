<?php

namespace App\Ark\Growth\Content;

use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Str;

/**
 * Converts a Growth opportunity content draft into a Common Problems page shape.
 */
final class ContentDraftToCommonProblemConverter
{
    /**
     * @return array<string, mixed>
     */
    public function convert(array $draft, ?string $searchQuery = null): array
    {
        $normalized = ContentBuilderSchema::normalize($draft, null, $searchQuery);
        $shop = ShopSettings::current();
        $shopName = trim((string) ($shop->shop_name ?? '')) ?: 'Demo Auto Repair';
        $slug = (string) $normalized['slug'];
        $title = (string) $normalized['title'];
        $query = trim((string) ($searchQuery ?? $title));

        $whatHappensNext = $this->linesFromDiagnosis((string) $normalized['diagnosis'], $shopName);

        return [
            'slug' => $slug,
            'title' => $title,
            'page_title' => $title,
            'tier' => 2,
            'concern_prefill' => $this->concernPrefill($query),
            'meta_description' => Str::limit((string) $normalized['summary'], 300, ''),
            'problem' => (string) $normalized['summary'],
            'symptoms' => $normalized['symptoms'],
            'can_drive_heading' => 'Can I keep driving?',
            'can_drive' => $normalized['when_not_to_drive'],
            'common_causes' => $normalized['common_causes'],
            'what_happens_next' => $whatHappensNext,
            'faq' => $normalized['faq'],
            'related_problem_slugs' => $this->relatedSlugs($normalized['related_problems']),
        ];
    }

    private function concernPrefill(string $query): string
    {
        $lower = strtolower($query);

        if (str_contains($lower, 'overheat')) {
            return 'My vehicle is overheating — '.$query.'.';
        }

        if (str_contains($lower, 'wobble') || str_contains($lower, 'shake') || str_contains($lower, 'vibrat')) {
            return 'My vehicle has steering shake or vibration — '.$query.'.';
        }

        if (preg_match('/^p\d+/i', $query)) {
            return 'My check engine light is on with code '.strtoupper($query).'.';
        }

        return 'I need help with '.$query.'.';
    }

    /**
     * @param  list<string>  $relatedProblems
     * @return list<string>
     */
    private function relatedSlugs(array $relatedProblems): array
    {
        return collect($relatedProblems)
            ->map(static fn (string $value): string => Str::slug($value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function linesFromDiagnosis(string $diagnosis, string $shopName): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($diagnosis)) ?: [];

        $lines = collect($sentences)
            ->map(static fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();

        if ($lines !== []) {
            return $lines;
        }

        return [
            'Tell us the symptom in your own words — when it happens and what changed recently.',
            "We inspect and test before recommending parts at {$shopName}.",
            'You see findings before any repair is approved.',
        ];
    }
}
