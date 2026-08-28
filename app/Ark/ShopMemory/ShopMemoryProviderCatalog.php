<?php

namespace App\Ark\ShopMemory;

/**
 * Fixed catalog of Shop Memory providers and surfaces.
 * Diagnostics compare catalog vs enablement vs engine registration.
 */
final class ShopMemoryProviderCatalog
{
    public const HISTORICAL_LABOR = 'historical_labor';

    public const HISTORICAL_CONCERN = 'historical_concern';

    public const TECHNICIAN_OBSERVATION = 'technician_observation';

    public const INSPECTION_FINDING = 'inspection_finding';

    public const CUSTOMER_INTAKE = 'customer_intake';

    public const AI_REWRITE = 'ai_rewrite';

    /**
     * @return list<array{key: string, name: string, description: string, version: string, corpora: list<string>}>
     */
    public static function providers(): array
    {
        return [
            [
                'key' => self::HISTORICAL_LABOR,
                'name' => 'Historical Labor',
                'description' => 'Reuses past labor line descriptions from this shop.',
                'version' => '1',
                'corpora' => ['work_language'],
            ],
            [
                'key' => self::HISTORICAL_CONCERN,
                'name' => 'Historical Concern',
                'description' => 'Reuses past concern summaries and customer problem language.',
                'version' => '1',
                'corpora' => ['problem_language'],
            ],
            [
                'key' => self::TECHNICIAN_OBSERVATION,
                'name' => 'Technician Observation',
                'description' => 'Surfaces verified findings and technician notes as suggestions.',
                'version' => '1',
                'corpora' => ['problem_language'],
            ],
            [
                'key' => self::INSPECTION_FINDING,
                'name' => 'Inspection Finding',
                'description' => 'Surfaces inspection notes and labels as suggestions.',
                'version' => '1',
                'corpora' => ['problem_language'],
            ],
            [
                'key' => self::CUSTOMER_INTAKE,
                'name' => 'Customer Intake',
                'description' => 'Surfaces visit-reason language as concern suggestions.',
                'version' => '1',
                'corpora' => ['problem_language'],
            ],
            [
                'key' => self::AI_REWRITE,
                'name' => 'AI Rewrite',
                'description' => 'Explicit Rewrite only — never automatic authorship.',
                'version' => '1',
                'corpora' => [],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function providerKeys(): array
    {
        return array_column(self::providers(), 'key');
    }

    /**
     * Default enablement matrix for new shops.
     *
     * @return array{providers: array<string, bool>, surfaces: array<string, bool>}
     */
    public static function defaultSettings(): array
    {
        return [
            'providers' => [
                self::HISTORICAL_LABOR => true,
                self::HISTORICAL_CONCERN => false,
                self::TECHNICIAN_OBSERVATION => false,
                self::INSPECTION_FINDING => false,
                self::CUSTOMER_INTAKE => false,
                self::AI_REWRITE => false,
            ],
            'surfaces' => [
                'add_concern_popup' => true,
            ],
        ];
    }
}
