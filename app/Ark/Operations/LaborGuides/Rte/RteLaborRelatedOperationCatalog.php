<?php

namespace App\Ark\Operations\LaborGuides\Rte;

/**
 * Related RTE job codes to offer with a primary job — real operations, not fees.
 *
 * Keys are parent job prefixes (first 4 chars of lab_id). Values are rte_job_lku codes.
 */
final class RteLaborRelatedOperationCatalog
{
    /** @var array<string, list<string>> */
    private const BY_PARENT_JOB_CODE = [
        '3461' => ['1421', '1431'],
        'CC14' => ['1421', '1431'],
        'CC15' => ['1421', '1431'],
        '3241' => ['1421', '1431'],
        '3233' => ['1421', '1431'],
        '3235' => ['1421', '1431'],
        '1451' => ['1421', '1431'],
        '1461' => ['1421', '1431'],
    ];

    /**
     * @return list<string>
     */
    public function relatedJobCodesForLabRow(array $row): array
    {
        $labId = strtoupper(trim((string) ($row['lab_id'] ?? '')));

        if (strlen($labId) < 4) {
            return [];
        }

        $parentJobCode = substr($labId, 0, 4);

        return self::BY_PARENT_JOB_CODE[$parentJobCode] ?? [];
    }
}
