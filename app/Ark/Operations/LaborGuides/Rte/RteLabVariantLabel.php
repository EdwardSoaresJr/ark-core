<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RteLabVariantLabel
{
    /** @var array<string, string|null> */
    private array $carDescriptionCache = [];

    /** @var Collection<int, object{eng_id_code: string, eng_desc: string, lo_yr: string, hi_yr: string}> */
    private Collection $enginesForVehicle;

    private ?int $modelYear = null;

    private ?RteLaborVehicleEngineProfile $engineProfile = null;

    public function __construct(
        private readonly RteEngineDescriptionFormatter $engineFormatter = new RteEngineDescriptionFormatter,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function attach(
        array $rows,
        string $selectedCarIdCode,
        ?int $modelYear = null,
        ?RteLaborVehicleEngineProfile $engineProfile = null,
    ): array {
        if ($rows === []) {
            return [];
        }

        $selectedCarIdCode = strtoupper(trim($selectedCarIdCode));
        $this->modelYear = $modelYear;
        $this->engineProfile = $engineProfile;
        $this->preloadCarDescriptions($rows, $selectedCarIdCode);
        $this->enginesForVehicle = DB::table('rte_engtbl')
            ->where('mod_id_code', $selectedCarIdCode)
            ->orderBy('eng_desc')
            ->get(['eng_id_code', 'eng_desc', 'lo_yr', 'hi_yr']);

        foreach ($rows as &$row) {
            $labId = (string) ($row['lab_id'] ?? '');
            $segment = (string) ($row['vehicle_segment'] ?? RteLabVehicleSegment::fromLabId($labId) ?? '');
            $row['lab_segment'] = $segment;
            $row['match_rank'] = RteLabVehicleSegment::matchRank($segment, $selectedCarIdCode);
            $row['variant_label'] = $this->forRow($row, $selectedCarIdCode, $segment);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function forRow(array $row, string $selectedCarIdCode, ?string $segment = null): string
    {
        $labId = (string) ($row['lab_id'] ?? '');
        $segment ??= (string) ($row['vehicle_segment'] ?? RteLabVehicleSegment::fromLabId($labId) ?? '');
        $selectedCarIdCode = strtoupper(trim($selectedCarIdCode));

        $parts = [];

        $engineLabel = $this->engineLabel($row);

        if ($engineLabel !== null) {
            $parts[] = $engineLabel;
        }

        $vehicleLabel = $this->vehicleLabel($segment, $selectedCarIdCode, $engineLabel !== null);

        if ($vehicleLabel !== null) {
            $parts[] = $vehicleLabel;
        }

        $yearLabel = $this->yearLabel($row);

        if ($yearLabel !== null) {
            $parts[] = $yearLabel;
        }

        if ($parts === []) {
            return $labId;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function preloadCarDescriptions(array $rows, string $selectedCarIdCode): void
    {
        $carCodes = [$selectedCarIdCode];

        foreach ($rows as $row) {
            $labId = (string) ($row['lab_id'] ?? '');
            $segment = (string) ($row['vehicle_segment'] ?? RteLabVehicleSegment::fromLabId($labId) ?? '');

            if ($segment !== '') {
                $carCodes[] = $segment;
            }
        }

        $carCodes = array_values(array_unique($carCodes));

        foreach (DB::table('rte_carlvl3')
            ->whereIn('car_id_code', $carCodes)
            ->orderBy('car_desc')
            ->get(['car_id_code', 'car_desc']) as $row) {
            $code = strtoupper(trim((string) $row->car_id_code));
            $this->carDescriptionCache[$code] = filled($row->car_desc)
                ? trim((string) $row->car_desc)
                : null;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function engineLabel(array $row): ?string
    {
        $labels = [];

        foreach (['eng1', 'eng2', 'eng3', 'eng4', 'eng5', 'eng6', 'eng7', 'eng8', 'eng9'] as $column) {
            $pattern = strtoupper(trim((string) ($row[$column] ?? '')));

            if ($pattern === '') {
                continue;
            }

            foreach ($this->matchingEngineDescriptions($pattern, $row) as $label) {
                $labels[$label] = true;
            }
        }

        if ($labels === []) {
            return null;
        }

        return implode(' · ', $this->dedupeEngineLabels(array_keys($labels)));
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function dedupeEngineLabels(array $labels): array
    {
        $labels = array_values(array_unique(array_filter($labels)));

        usort($labels, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        $kept = [];

        foreach ($labels as $label) {
            $duplicate = false;

            foreach ($kept as $existing) {
                if ($label === $existing || str_contains($existing, $label)) {
                    $duplicate = true;
                    break;
                }
            }

            if (! $duplicate) {
                $kept[] = $label;
            }
        }

        sort($kept);

        return $kept;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function matchingEngineDescriptions(string $pattern, array $row): array
    {
        if ($this->engineProfile !== null && strpbrk($pattern, 'xX') !== false) {
            $preferredEngine = $this->engineProfile->preferredEngineForPattern($pattern);

            if ($preferredEngine !== null) {
                $formatted = $this->engineFormatter->format((string) $preferredEngine->eng_desc);

                if ($formatted !== '') {
                    return [$formatted];
                }
            }
        }

        $labels = [];

        foreach ($this->enginesForVehicle as $engine) {
            if (! $this->enginePatternMatches((string) $engine->eng_id_code, $pattern)) {
                continue;
            }

            if (! $this->engineAppliesToLabRow($engine, $row)) {
                continue;
            }

            $formatted = $this->engineFormatter->format((string) $engine->eng_desc);

            if ($formatted !== '') {
                $labels[] = $formatted;
            }
        }

        return $labels;
    }

    /**
     * @param  object{lo_yr: string, hi_yr: string}  $engine
     * @param  array<string, mixed>  $row
     */
    private function engineAppliesToLabRow(object $engine, array $row): bool
    {
        $labLoYear = $this->normalizeYear((string) ($row['lo_yr'] ?? ''));
        $labHiYear = $this->normalizeYear((string) ($row['hi_yr'] ?? ''));
        $engineLoYear = $this->normalizeYear((string) $engine->lo_yr);
        $engineHiYear = $this->normalizeYear((string) $engine->hi_yr);

        $effectiveLoYear = max(
            $labLoYear ?? 0,
            $engineLoYear ?? 0,
            $this->modelYear ?? 0,
        );

        $effectiveHiYear = min(
            $labHiYear ?? 9999,
            $engineHiYear ?? 9999,
            $this->modelYear ?? 9999,
        );

        if ($this->modelYear !== null) {
            return $effectiveLoYear <= $this->modelYear && $effectiveHiYear >= $this->modelYear;
        }

        if ($labLoYear !== null && $engineHiYear !== null && $labLoYear > $engineHiYear) {
            return false;
        }

        if ($labHiYear !== null && $engineLoYear !== null && $labHiYear < $engineLoYear) {
            return false;
        }

        return true;
    }

    private function normalizeYear(string $year): ?int
    {
        $year = trim($year);

        if ($year === '' || $year === '0000') {
            return null;
        }

        if ($year === '9999') {
            return null;
        }

        return (int) $year;
    }

    private function enginePatternMatches(string $engineCode, string $pattern): bool
    {
        return (new RteEnginePatternMatcher)->matches($engineCode, $pattern);
    }

    private function vehicleLabel(string $segment, string $selectedCarIdCode, bool $hasEngineLabel): ?string
    {
        if ($segment === '' || $hasEngineLabel) {
            return null;
        }

        if ($segment === $selectedCarIdCode) {
            return $this->carDescription($selectedCarIdCode) ?? $selectedCarIdCode;
        }

        if (strpbrk($segment, 'xX') !== false && RteLabVehicleSegment::matchesCar($segment, $selectedCarIdCode)) {
            return null;
        }

        if (strpbrk($segment, 'xX') !== false) {
            return $this->carDescription($selectedCarIdCode) ?? $selectedCarIdCode;
        }

        return $this->carDescription($segment) ?? $segment;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function yearLabel(array $row): ?string
    {
        $loYear = trim((string) ($row['lo_yr'] ?? ''));
        $hiYear = trim((string) ($row['hi_yr'] ?? ''));

        if ($loYear === '' || $hiYear === '') {
            return null;
        }

        if ($loYear === '0000' && $hiYear === '9999') {
            return 'All years';
        }

        if ($loYear === '0000') {
            return 'Through '.$hiYear;
        }

        if ($hiYear === '9999') {
            return $loYear.'+';
        }

        if ($loYear === $hiYear) {
            return $loYear;
        }

        return $loYear.'–'.$hiYear;
    }

    private function carDescription(string $carIdCode): ?string
    {
        if (array_key_exists($carIdCode, $this->carDescriptionCache)) {
            return $this->carDescriptionCache[$carIdCode];
        }

        $description = DB::table('rte_carlvl3')
            ->where('car_id_code', $carIdCode)
            ->orderBy('car_desc')
            ->value('car_desc');

        $this->carDescriptionCache[$carIdCode] = filled($description)
            ? trim((string) $description)
            : null;

        return $this->carDescriptionCache[$carIdCode];
    }
}
