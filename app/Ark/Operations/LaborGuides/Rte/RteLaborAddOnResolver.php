<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use Illuminate\Support\Facades\DB;

final class RteLaborAddOnResolver
{
    /**
     * RTE bundled add-on codes that are fees or prep — not billable labor lines here.
     *
     * @var list<string>
     */
    private const EXCLUDED_ADD_IDS = [
        '100', // coolant recovery / service prep
        '1500', // shop supplies
    ];

    /** @var array<string, string|null> */
    private array $descriptionCache = [];

    public function __construct(
        private readonly RteLaborAddIdCatalog $catalog = new RteLaborAddIdCatalog,
    ) {}

    /**
     * Bundled fractional add-ons that resolve to real RTE job descriptions only.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{
     *     kind: string,
     *     add_id: string,
     *     lab_id: null,
     *     description: string,
     *     lo_hr: float,
     *     avg_hr: float,
     *     hi_hr: float
     * }>
     */
    public function forLabRow(array $row): array
    {
        $addOns = [];

        for ($index = 1; $index <= 9; $index++) {
            $addId = trim((string) ($row['add_id'.$index] ?? ''));

            if ($addId === '' || $this->isExcluded($addId)) {
                continue;
            }

            $hours = $row['add_hr'.$index] ?? null;

            if ($hours === null || $hours === '' || (float) $hours <= 0) {
                continue;
            }

            $description = $this->lookupDescription($addId);

            if ($description === null) {
                continue;
            }

            $rounded = round((float) $hours, 2);

            $addOns[] = [
                'kind' => 'bundled_add_on',
                'add_id' => $addId,
                'lab_id' => null,
                'description' => $description,
                'lo_hr' => $rounded,
                'avg_hr' => $rounded,
                'hi_hr' => $rounded,
            ];
        }

        return $addOns;
    }

    private function isExcluded(string $addId): bool
    {
        return in_array(trim($addId), self::EXCLUDED_ADD_IDS, true);
    }

    private function lookupDescription(string $addId): ?string
    {
        $addId = trim($addId);
        $cacheKey = $addId;

        if (array_key_exists($cacheKey, $this->descriptionCache)) {
            return $this->descriptionCache[$cacheKey];
        }

        $exact = DB::table('rte_job_lku')
            ->where('job_id_code', $addId)
            ->value('job_desc');

        if (filled($exact)) {
            return $this->descriptionCache[$cacheKey] = trim((string) $exact);
        }

        if (strlen($addId) >= 3) {
            $prefix = DB::table('rte_job_lku')
                ->where('job_id_code', 'like', $addId.'%')
                ->orderBy('job_id_code')
                ->value('job_desc');

            if (filled($prefix)) {
                return $this->descriptionCache[$cacheKey] = trim((string) $prefix);
            }
        }

        $this->descriptionCache[$cacheKey] = null;

        return null;
    }
}
