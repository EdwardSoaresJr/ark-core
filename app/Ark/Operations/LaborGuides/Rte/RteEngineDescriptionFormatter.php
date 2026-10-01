<?php

namespace App\Ark\Operations\LaborGuides\Rte;

/**
 * Short, advisor-readable labels from RTE eng_desc strings.
 */
final class RteEngineDescriptionFormatter
{
    public function format(string $engDesc): string
    {
        $engDesc = trim($engDesc);

        if ($engDesc === '') {
            return '';
        }

        $liters = $this->litersFromDescription($engDesc);
        $name = $this->engineNameFromDescription($engDesc);
        $cylinders = $this->cylinderHintFromDescription($engDesc);

        if ($liters !== null && $name !== null) {
            return $cylinders !== null
                ? sprintf('%s %s %s', $liters, $name, $cylinders)
                : sprintf('%s %s', $liters, $name);
        }

        if ($liters !== null) {
            return $liters;
        }

        return $this->truncateDescription($engDesc);
    }

    private function litersFromDescription(string $engDesc): ?string
    {
        if (preg_match('/\b(\d\.\d)\s*L\b/i', $engDesc, $match) === 1) {
            return rtrim(rtrim(number_format((float) $match[1], 1, '.', ''), '0'), '.').'L';
        }

        if (preg_match('/\b(\d{3,4})cc\b/i', $engDesc, $match) === 1) {
            $liters = round(((int) $match[1]) / 1000, 1);

            return rtrim(rtrim(number_format($liters, 1, '.', ''), '0'), '.').'L';
        }

        return null;
    }

    private function engineNameFromDescription(string $engDesc): ?string
    {
        if (preg_match('/\(([^)]+)\)/', $engDesc, $match) === 1) {
            $parenthetical = trim($match[1]);

            if ($parenthetical !== '' && ! str_contains(strtoupper($parenthetical), 'CYLINDER')) {
                return strtoupper($parenthetical);
            }
        }

        if (preg_match('/\b(22RTE|22RE|22R|3VZE|5SFE|4AFE|4A SERIES|HEMI|CUMMINS|POWERSTROKE|DURAMAX)\b/i', $engDesc, $match) === 1) {
            return strtoupper($match[1]);
        }

        if (preg_match('/\b(V6|V8|I4|I6)\b/i', $engDesc, $match) === 1) {
            return strtoupper($match[1]);
        }

        return null;
    }

    private function cylinderHintFromDescription(string $engDesc): ?string
    {
        if (preg_match('/\b(\d+)\s*CYL(?:INDER)?\b/i', $engDesc, $match) === 1) {
            return $match[1].'-cyl';
        }

        if (preg_match('/\bV6\b/i', $engDesc) === 1) {
            return 'V6';
        }

        if (preg_match('/\bV8\b/i', $engDesc) === 1) {
            return 'V8';
        }

        return null;
    }

    private function truncateDescription(string $engDesc): string
    {
        $compact = preg_replace('/\s+/', ' ', $engDesc) ?? $engDesc;

        if (strlen($compact) <= 32) {
            return $compact;
        }

        return rtrim(substr($compact, 0, 29)).'…';
    }
}
