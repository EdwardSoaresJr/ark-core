<?php

namespace App\Ark\Operations\Labor;

/**
 * Merges diagnostic overlap observation into labor explanation payloads.
 */
final class LaborExplanationDiagnosticOverlap
{
    /**
     * @param  array<string, mixed>  $laborExplanation
     * @param  array<string, mixed>|null  $overlap
     * @return array<string, mixed>
     */
    public function attachToExplanation(array $laborExplanation, ?array $overlap): array
    {
        if ($overlap === null) {
            return $laborExplanation;
        }

        $laborExplanation['diagnostic_overlap'] = [
            'advisor_summary' => $overlap['advisor_summary'],
            'advisor_detail' => $overlap['advisor_detail'],
        ];

        return $laborExplanation;
    }
}
