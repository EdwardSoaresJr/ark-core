<?php

namespace App\Ark\Operations\LaborGuides\Rte;

/**
 * Human labels for RTE add_id codes that are not exported as job_lku rows.
 *
 * RTE stores bundled time in add_id/add_hr pairs using internal numeric codes.
 * When job_lku lookup fails, use global or parent-job-family labels here.
 */
final class RteLaborAddIdCatalog
{
    /** @var array<string, string> */
    private const GLOBAL = [
        '125' => '4 WHEEL ALIGNMENT',
        '135' => 'FLUSH BRAKE SYSTEM',
        '9119' => 'R & R 5TH MEMBER',
    ];

    /**
     * Labels keyed by parent job prefix family (first two characters of lab_id job code).
     *
     * @var array<string, array<string, string>>
     */
    private const BY_JOB_FAMILY = [
        '34' => [
            '100' => 'COOLANT RECOVERY & DISPOSAL',
            '1500' => 'SHOP SUPPLIES',
        ],
        '14' => [
            '100' => 'COOLANT RECOVERY & DISPOSAL',
            '1500' => 'SHOP SUPPLIES',
        ],
        '33' => [
            '100' => 'COOLANT RECOVERY & DISPOSAL',
            '1500' => 'SHOP SUPPLIES',
        ],
        '32' => [
            '100' => 'ENGINE SERVICE PREP',
            '1500' => 'SHOP SUPPLIES',
        ],
        '72' => [
            '100' => 'ENGINE SERVICE PREP',
            '1500' => 'SHOP SUPPLIES',
        ],
        '73' => [
            '100' => 'ENGINE SERVICE PREP',
            '1500' => 'SHOP SUPPLIES',
        ],
        '74' => [
            '100' => 'ENGINE SERVICE PREP',
            '1500' => 'SHOP SUPPLIES',
        ],
        '77' => [
            '100' => 'ENGINE SERVICE PREP',
            '1500' => 'SHOP SUPPLIES',
        ],
        '62' => [
            '1800' => 'ELECTRICAL CIRCUIT TEST',
            '1500' => 'SHOP SUPPLIES',
        ],
        '63' => [
            '1800' => 'ELECTRICAL CIRCUIT TEST',
            '1500' => 'SHOP SUPPLIES',
        ],
        '39' => [
            '1800' => 'ELECTRICAL CIRCUIT TEST',
            '1500' => 'SHOP SUPPLIES',
        ],
    ];

    public function describe(string $addId, ?string $parentJobPrefix = null): ?string
    {
        $addId = trim($addId);

        if ($addId === '') {
            return null;
        }

        if (isset(self::GLOBAL[$addId])) {
            return self::GLOBAL[$addId];
        }

        $family = $this->jobFamily($parentJobPrefix);

        if ($family !== null && isset(self::BY_JOB_FAMILY[$family][$addId])) {
            return self::BY_JOB_FAMILY[$family][$addId];
        }

        return null;
    }

    public function fallbackLabel(string $addId): string
    {
        return 'RTE add-on '.$addId;
    }

    private function jobFamily(?string $parentJobPrefix): ?string
    {
        $parentJobPrefix = strtoupper(trim((string) $parentJobPrefix));

        if (strlen($parentJobPrefix) < 2) {
            return null;
        }

        return substr($parentJobPrefix, 0, 2);
    }
}
