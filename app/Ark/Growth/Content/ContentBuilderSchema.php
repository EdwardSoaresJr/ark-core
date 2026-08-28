<?php

namespace App\Ark\Growth\Content;

use Illuminate\Support\Str;

final class ContentBuilderSchema
{
    /**
     * @return list<array{key: string, label: string, type: string, required: bool, hint: string|null}>
     */
    public static function sections(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'hint' => 'H1 and meta title source'],
            ['key' => 'slug', 'label' => 'Slug', 'type' => 'text', 'required' => true, 'hint' => 'URL segment under /common-problems/'],
            ['key' => 'summary', 'label' => 'Summary', 'type' => 'textarea', 'required' => true, 'hint' => 'Lead paragraph and meta description source'],
            ['key' => 'symptoms', 'label' => 'Symptoms', 'type' => 'lines', 'required' => true, 'hint' => 'One symptom per line'],
            ['key' => 'diagnosis', 'label' => 'Diagnosis', 'type' => 'textarea', 'required' => true, 'hint' => 'What the shop checks first'],
            ['key' => 'common_causes', 'label' => 'Common causes', 'type' => 'lines', 'required' => true, 'hint' => 'One cause per line'],
            ['key' => 'when_not_to_drive', 'label' => 'When not to drive', 'type' => 'lines', 'required' => true, 'hint' => 'Safety guidance'],
            ['key' => 'faq', 'label' => 'FAQ', 'type' => 'faq', 'required' => true, 'hint' => 'Question and answer pairs'],
            ['key' => 'cta', 'label' => 'CTA', 'type' => 'text', 'required' => true, 'hint' => 'Primary call to action copy'],
            ['key' => 'related_services', 'label' => 'Related services', 'type' => 'lines', 'required' => false, 'hint' => 'Internal service links'],
            ['key' => 'related_problems', 'label' => 'Related problems', 'type' => 'lines', 'required' => false, 'hint' => 'Other common problem slugs'],
            ['key' => 'related_vehicles', 'label' => 'Related vehicles', 'type' => 'lines', 'required' => false, 'hint' => 'Make/model lines when relevant'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyDraft(?string $title = null, ?string $searchQuery = null): array
    {
        $label = self::stripActionPrefix($title ?? ($searchQuery !== null ? Str::title($searchQuery) : ''));
        $slug = $searchQuery !== null ? Str::slug($searchQuery) : '';

        return [
            'title' => $label,
            'slug' => $slug,
            'summary' => '',
            'symptoms' => [],
            'diagnosis' => '',
            'common_causes' => [],
            'when_not_to_drive' => [],
            'faq' => [],
            'cta' => 'Talk to a service advisor',
            'related_services' => [],
            'related_problems' => [],
            'related_vehicles' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $draft
     * @return array<string, mixed>
     */
    public static function normalize(?array $draft, ?string $fallbackTitle = null, ?string $searchQuery = null): array
    {
        $base = self::emptyDraft($fallbackTitle, $searchQuery);

        if ($draft === null) {
            return $base;
        }

        return [
            'title' => self::stripActionPrefix(trim((string) ($draft['title'] ?? $base['title']))),
            'slug' => Str::slug((string) ($draft['slug'] ?? $base['slug'])),
            'summary' => trim((string) ($draft['summary'] ?? '')),
            'symptoms' => self::normalizeLines($draft['symptoms'] ?? []),
            'diagnosis' => trim((string) ($draft['diagnosis'] ?? '')),
            'common_causes' => self::normalizeLines($draft['common_causes'] ?? []),
            'when_not_to_drive' => self::normalizeLines($draft['when_not_to_drive'] ?? []),
            'faq' => self::normalizeFaq($draft['faq'] ?? []),
            'cta' => trim((string) ($draft['cta'] ?? $base['cta'])),
            'related_services' => self::normalizeLines($draft['related_services'] ?? []),
            'related_problems' => self::normalizeLines($draft['related_problems'] ?? []),
            'related_vehicles' => self::normalizeLines($draft['related_vehicles'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    private static function normalizeLines(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(static fn ($line): string => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private static function normalizeFaq(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function ($item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $question = trim((string) ($item['question'] ?? ''));
                $answer = trim((string) ($item['answer'] ?? ''));

                if ($question === '' && $answer === '') {
                    return null;
                }

                return ['question' => $question, 'answer' => $answer];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return list<string>
     */
    public static function incompleteRequiredKeys(array $draft): array
    {
        $missing = [];

        foreach (self::sections() as $section) {
            if (! $section['required']) {
                continue;
            }

            $key = $section['key'];
            $value = $draft[$key] ?? null;

            $empty = match ($section['type']) {
                'lines', 'faq' => ! is_array($value) || $value === [],
                'textarea', 'text' => ! is_string($value) || trim($value) === '',
                default => blank($value),
            };

            if ($empty) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public static function previewPath(array $draft): ?string
    {
        $slug = Str::slug((string) ($draft['slug'] ?? ''));

        return $slug !== '' ? '/common-problems/'.$slug : null;
    }

    /**
     * @param  array<string, mixed>  $seeded
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public static function mergePreferFilled(array $seeded, ?array $existing): array
    {
        if ($existing === null) {
            return $seeded;
        }

        $prior = self::normalize($existing);
        $merged = $seeded;

        foreach ($prior as $key => $value) {
            if (in_array($key, ['symptoms', 'common_causes', 'when_not_to_drive', 'faq', 'related_services', 'related_problems', 'related_vehicles'], true)) {
                if (is_array($value) && $value !== []) {
                    $merged[$key] = $value;
                }

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $merged[$key] = $value;
            }
        }

        return self::normalize($merged);
    }

    public static function needsSeeding(?array $draft): bool
    {
        return self::incompleteRequiredKeys(self::normalize($draft)) !== [];
    }

    /**
     * Queue labels use "Create:" / "Improve:" — page H1 and meta title never do.
     */
    public static function stripActionPrefix(string $title): string
    {
        $stripped = preg_replace('/^(Create|Improve):\s*/i', '', trim($title));

        return is_string($stripped) ? trim($stripped) : trim($title);
    }
}
