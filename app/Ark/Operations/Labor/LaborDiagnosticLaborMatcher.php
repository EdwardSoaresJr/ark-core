<?php

namespace App\Ark\Operations\Labor;

/**
 * Identifies diagnostic/testing labor descriptions for overlap observation.
 */
final class LaborDiagnosticLaborMatcher
{
    /** @var list<string> */
    private const KEYWORD_PHRASES = [
        'COMBUSTION',
        'PRESSURE TEST',
        'LEAK TEST',
        'DIAGNOS',
        'VERIFY',
        'INSPECTION',
        'PERFORM TEST',
        'SYSTEM TEST',
        'TEST COOL',
        'COOLING SYSTEM TEST',
        'COOLANT TEST',
    ];

    public function isDiagnosticTestingDescription(string $description): bool
    {
        $normalized = strtoupper(trim($description));

        if ($normalized === '') {
            return false;
        }

        foreach (self::KEYWORD_PHRASES as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        if (preg_match('/\bTEST\b/', $normalized) !== 1) {
            return false;
        }

        return str_contains($normalized, 'COOL')
            || str_contains($normalized, 'COMBUST')
            || str_contains($normalized, 'PRESSURE')
            || str_contains($normalized, 'SYSTEM')
            || str_contains($normalized, 'DIAGNOS');
    }
}
