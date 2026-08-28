<?php

namespace App\Ark\Growth\Content;

use Illuminate\Support\Str;

/**
 * Maps a growth content draft into the Common Problems page shape for staff preview.
 */
final class ContentDraftProblemProjection
{
    /**
     * @param  array<string, mixed>  $draft
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
     *     path: string,
     *     faq?: list<array{question: string, answer: string}>,
     *     related_problem_slugs?: list<string>,
     * }
     */
    public static function fromDraft(array $draft): array
    {
        $draft = ContentBuilderSchema::normalize($draft);
        $slug = Str::slug((string) ($draft['slug'] ?? ''));
        $title = (string) ($draft['title'] ?? '');
        $summary = (string) ($draft['summary'] ?? '');
        $diagnosis = trim((string) ($draft['diagnosis'] ?? ''));

        $whatHappensNext = self::whatHappensNext($diagnosis);
        $whenNotToDrive = $draft['when_not_to_drive'] ?? [];

        return [
            'slug' => $slug,
            'title' => $title,
            'tier' => 2,
            'concern_prefill' => $title !== '' ? $title : $summary,
            'meta_description' => $summary,
            'problem' => $summary,
            'symptoms' => $draft['symptoms'] ?? [],
            'can_drive_heading' => 'When not to drive',
            'can_drive' => $whenNotToDrive !== []
                ? $whenNotToDrive
                : ['Schedule inspection if the symptom is new, worsening, or paired with warning lights.'],
            'common_causes' => $draft['common_causes'] ?? [],
            'what_happens_next' => $whatHappensNext,
            'faq' => $draft['faq'] ?? [],
            'related_problem_slugs' => collect($draft['related_problems'] ?? [])
                ->map(static fn (string $value): string => Str::slug($value))
                ->filter()
                ->values()
                ->all(),
            'path' => $slug !== '' ? '/common-problems/'.$slug : '/common-problems',
        ];
    }

    /**
     * @return list<string>
     */
    private static function whatHappensNext(string $diagnosis): array
    {
        if ($diagnosis === '') {
            return [
                'Tell us what you are experiencing — when it started and whether it is getting worse.',
                'We inspect the systems tied to your concern before recommending parts.',
                'You see what we find before any work is authorized.',
            ];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $diagnosis) ?: [];

        return collect($sentences)
            ->map(static fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
