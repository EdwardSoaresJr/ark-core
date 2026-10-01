<?php

namespace App\Ark\Website;

/**
 * Complaint and code pages use an explicit presentation.
 * Job articles and local landings stay on the article renderer.
 */
final class PublicProblemPresentation
{
    public const COMPLAINT = 'complaint';

    public const CODE = 'code';

    public static function type(string $slug): ?string
    {
        if (in_array($slug, PublicProblemGroups::SYMPTOM_SLUGS, true)) {
            return self::COMPLAINT;
        }

        if (in_array($slug, PublicProblemGroups::CODE_SLUGS, true)) {
            return self::CODE;
        }

        return null;
    }

    /**
     * @return list<array{key: string, heading: string|null}>
     */
    public static function sections(string $type): array
    {
        if ($type === self::CODE) {
            return [
                ['key' => 'symptoms', 'heading' => 'Symptoms'],
                ['key' => 'can_drive', 'heading' => null],
                ['key' => 'often_confused_with', 'heading' => 'Often confused with'],
                ['key' => 'common_causes', 'heading' => 'Common causes'],
                ['key' => 'if_you_ignore', 'heading' => 'If you ignore it'],
                ['key' => 'repair_overview', 'heading' => 'Repair overview'],
                ['key' => 'what_happens_next', 'heading' => 'What happens next'],
            ];
        }

        return [
            ['key' => 'symptoms', 'heading' => 'Symptoms'],
            ['key' => 'common_causes', 'heading' => 'Common causes'],
            ['key' => 'can_drive', 'heading' => null],
            ['key' => 'often_confused_with', 'heading' => 'Often confused with'],
            ['key' => 'if_you_ignore', 'heading' => 'If you ignore it'],
            ['key' => 'repair_overview', 'heading' => 'Repair overview'],
            ['key' => 'what_happens_next', 'heading' => 'What happens next'],
        ];
    }

    public static function relatedHeading(string $type): string
    {
        return $type === self::CODE
            ? 'Related symptoms and codes'
            : 'Related problems and codes';
    }

    public static function closeHeading(string $type): string
    {
        return $type === self::CODE
            ? 'Have this code on your vehicle?'
            : 'Still dealing with this problem?';
    }

    public static function closeLede(string $type): string
    {
        return $type === self::CODE
            ? 'A code gives us a place to start. Testing tells us what actually failed.'
            : 'Tell us what the vehicle is doing. We\'ll start with the evidence.';
    }
}
