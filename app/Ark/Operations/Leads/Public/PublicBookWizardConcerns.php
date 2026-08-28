<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Presentation choices for the public book wizard.
 * Prefills the Lead concern string — not a new scheduling authority.
 */
final class PublicBookWizardConcerns
{
    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return [
            'Check Engine Light',
            'Strange Noise',
            'Brakes',
            'A/C',
            'Maintenance',
            'Electrical',
            'Suspension / Steering',
            'Pre-Purchase Inspection',
            'Something Else',
        ];
    }

    public const SOMETHING_ELSE = 'Something Else';

    public const INTENT_SCHEDULE = 'Schedule Service';

    public const INTENT_OIL = 'Oil Change';

    /**
     * Concierge intents on Vehicle Home — not the guest wizard chip list.
     *
     * @return list<string>
     */
    public static function conciergeIntents(): array
    {
        return [
            self::INTENT_SCHEDULE,
            self::INTENT_OIL,
            'Check Engine Light',
            self::SOMETHING_ELSE,
        ];
    }

    /**
     * Map a prefilled concern string onto a chip when the match is obvious.
     */
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
            'Brakes' => ['brake', 'brakes'],
            'A/C' => ['a/c', 'ac ', 'air conditioning', 'not cold'],
            'Maintenance' => ['maintenance', 'oil change', 'tune up', 'tune-up'],
            'Electrical' => ['electrical', 'battery', 'alternator', 'wiring'],
            'Suspension / Steering' => ['suspension', 'steering', 'alignment', 'shock', 'strut'],
            'Pre-Purchase Inspection' => ['pre-purchase', 'ppi', 'pre purchase', 'buying a'],
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
