<?php

namespace App\Ark\Operations\Leads\Public;

final class PublicBookWizardConcerns
{
    public const SOMETHING_ELSE = 'Something Else';

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return [
            'Check Engine Light',
            'Diagnostics',
            'Strange Noise',
            'Brakes',
            'A/C',
            'Maintenance',
            'Electrical',
            'Suspension / Steering',
            'Pre-Purchase Inspection',
            self::SOMETHING_ELSE,
        ];
    }

    public static function matchCategory(?string $concern): ?string
    {
        $concern = trim((string) $concern);

        if ($concern === '') {
            return null;
        }

        foreach (self::options() as $option) {
            if ($option === self::SOMETHING_ELSE) {
                continue;
            }

            if (strcasecmp($concern, $option) === 0) {
                return $option;
            }
        }

        $haystack = strtolower($concern);
        $patterns = [
            'Check Engine Light' => ['check engine', 'cel', 'engine light'],
            'Strange Noise' => ['noise', 'rattle', 'squeak', 'clunk', 'grinding'],
            'Brakes' => ['brake'],
            'A/C' => ['a/c', 'air conditioning', 'not cold'],
            'Maintenance' => ['maintenance', 'oil change', 'tune up', 'tune-up'],
            'Electrical' => ['electrical', 'battery', 'alternator'],
            'Suspension / Steering' => ['suspension', 'steering', 'alignment'],
            'Pre-Purchase Inspection' => ['pre-purchase', 'ppi', 'pre purchase'],
            'Diagnostics' => ['diagnos'],
        ];

        foreach ($patterns as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $category;
                }
            }
        }

        return self::SOMETHING_ELSE;
    }

    public static function composeConcern(?string $category, ?string $details): string
    {
        $category = trim((string) $category);
        $details = trim((string) $details);

        if ($category === '' || $category === self::SOMETHING_ELSE) {
            return $details;
        }

        if ($details === '') {
            return $category;
        }

        return $category."\n\n".$details;
    }
}
