<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Word count from problem authority fields — not meta description alone.
 */
final class CommonProblemAuthorityWordCount
{
    /**
     * @param  array<string, mixed>  $problem
     */
    public static function count(array $problem): int
    {
        $chunks = [
            (string) ($problem['problem'] ?? ''),
            (string) ($problem['title'] ?? ''),
            (string) ($problem['meta_description'] ?? ''),
        ];

        foreach ([
            'symptoms',
            'can_drive',
            'common_causes',
            'often_confused_with',
            'if_you_ignore',
            'diagnostic_process',
            'typical_repairs',
            'repair_overview',
            'what_happens_next',
        ] as $listKey) {
            foreach ($problem[$listKey] ?? [] as $line) {
                $chunks[] = (string) $line;
            }
        }

        foreach ($problem['faq'] ?? [] as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $chunks[] = (string) ($faq['question'] ?? '');
            $chunks[] = (string) ($faq['answer'] ?? '');
        }

        $text = strip_tags(implode(' ', array_filter($chunks, fn (string $chunk): bool => trim($chunk) !== '')));

        return str_word_count($text);
    }
}
