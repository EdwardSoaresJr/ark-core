<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Vehicles\VehicleMatchProjection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only RTE labor guide lookups — mirrors export/example_join_view.sql.
 */
final class RteLaborLookup
{
    private const SEARCH_CANDIDATE_LIMIT = 200;

    /** @var list<string> */
    private const LAB_SELECT_COLUMNS = [
        'l.lab_id',
        'l.lo_yr',
        'l.hi_yr',
        'l.add_req',
        'l.model1',
        'l.model2',
        'l.model3',
        'l.eng1',
        'l.eng2',
        'l.eng3',
        'l.hi_hr',
        'l.avg_hr',
        'l.lo_hr',
        'l.add_id1',
        'l.add_hr1',
        'l.add_id2',
        'l.add_hr2',
        'l.add_id3',
        'l.add_hr3',
        'l.add_id4',
        'l.add_hr4',
        'l.add_id5',
        'l.add_hr5',
        'l.add_id6',
        'l.add_hr6',
        'l.add_id7',
        'l.add_hr7',
        'l.add_id8',
        'l.add_hr8',
        'l.add_id9',
        'l.add_hr9',
    ];

    private static ?bool $hasVehicleSegmentColumn = null;

    public function __construct(
        private readonly RteLabVariantLabel $variantLabels = new RteLabVariantLabel,
        private readonly RteLaborAddOnResolver $addOns = new RteLaborAddOnResolver,
        private readonly RteLaborRelatedOperationCatalog $relatedOperations = new RteLaborRelatedOperationCatalog,
        private readonly RteLaborRelatedOperationDoctrine $relatedOperationDoctrine = new RteLaborRelatedOperationDoctrine,
        private readonly RteLaborJobRecommender $recommender = new RteLaborJobRecommender,
        private readonly RteLaborSuggestedPackageBuilder $suggestedPackage = new RteLaborSuggestedPackageBuilder,
        private readonly RteShopLaborHoursProjection $shopHours = new RteShopLaborHoursProjection,
        private readonly RteLaborExplanationAttacher $laborExplanations = new RteLaborExplanationAttacher,
        private readonly VehicleMatchProjection $vehicleMatch = new VehicleMatchProjection,
    ) {}

    /**
     * @return array{
     *     recommended_job: array<string, mixed>|null,
     *     suggested_labor: array<string, mixed>|null,
     *     jobs: list<array<string, mixed>>,
     *     vehicle_engine_label: string|null,
     * }
     */
    public function searchWithRecommendation(
        string $carIdCode,
        ?int $modelYear,
        ?string $term,
        ?Vehicle $vehicle = null,
        ?RepairOrder $repairOrder = null,
        ?int $concernId = null,
        int $limit = 40,
        ?string $selectedEngIdCode = null,
    ): array {
        $engineProfile = RteLaborVehicleEngineProfile::forVehicle(
            $vehicle,
            $carIdCode,
            $modelYear,
            $selectedEngIdCode,
        );
        $engineSelectionRequired = $engineProfile->engineSelectionRequired($vehicle, $selectedEngIdCode);
        $matchContext = $this->matchContext(
            carIdCode: $carIdCode,
            modelYear: $modelYear,
            vehicle: $vehicle,
            engineProfile: $engineProfile,
        );
        $vehicleMatch = $this->vehicleMatch->build(
            vehicle: $vehicle,
            engineProfile: $engineProfile,
            applicationLabel: $matchContext['selected_application'] ?? null,
            engineSelectionRequired: $engineSelectionRequired,
        );

        $jobs = $this->searchJobs($carIdCode, $modelYear, $term, $limit, $engineProfile);
        $partitioned = $this->recommender->partition($jobs, $term, $engineProfile);

        if ($engineSelectionRequired) {
            $partitioned['recommended_job'] = null;
        }

        $allJobs = $partitioned['recommended_job'] !== null
            ? [$partitioned['recommended_job'], ...$partitioned['jobs']]
            : $jobs;

        $suggestedLabor = $engineSelectionRequired
            ? null
            : $this->suggestedPackage->build(
                $partitioned['recommended_job'],
                $partitioned['jobs'],
                $allJobs,
                $engineProfile,
                $term,
            );

        $results = [
            ...$partitioned,
            'suggested_labor' => $suggestedLabor,
            'vehicle_engine_label' => $engineProfile->primaryEngineLabel(),
            'vehicle_match' => $vehicleMatch,
            'engine_options' => $engineProfile->engineOptions(),
            'eng_id_code' => $engineProfile->selectedEngIdCode(),
        ];

        return $this->laborExplanations->attach(
            $results,
            $modelYear,
            $repairOrder,
            $concernId,
            $matchContext,
        );
    }

    public function applicationLabelForCar(string $carIdCode): ?string
    {
        $meta = $this->vehicleMeta($carIdCode);
        $applicationYears = DB::table('rte_carlvl3')
            ->where('car_id_code', $carIdCode)
            ->orderBy('lo_yr')
            ->first(['lo_yr', 'hi_yr']);

        $yearRange = $applicationYears !== null
            ? sprintf('%s-%s', (string) $applicationYears->lo_yr, (string) $applicationYears->hi_yr)
            : null;

        return filled($meta['car_desc'] ?? null)
            ? trim((string) $meta['car_desc']).($yearRange ? ' ('.$yearRange.')' : '')
            : null;
    }

    /**
     * @return array{
     *     car_id_code: string,
     *     selected_application: string|null,
     *     vehicle_label: string|null,
     *     vehicle_engine_label: string|null,
     * }
     */
    private function matchContext(
        string $carIdCode,
        ?int $modelYear,
        ?Vehicle $vehicle,
        RteLaborVehicleEngineProfile $engineProfile,
    ): array {
        $meta = $this->vehicleMeta($carIdCode);
        $applicationYears = DB::table('rte_carlvl3')
            ->where('car_id_code', $carIdCode)
            ->orderBy('lo_yr')
            ->first(['lo_yr', 'hi_yr']);

        $yearRange = $applicationYears !== null
            ? sprintf('%s-%s', (string) $applicationYears->lo_yr, (string) $applicationYears->hi_yr)
            : null;

        $applicationLabel = filled($meta['car_desc'] ?? null)
            ? trim((string) $meta['car_desc']).($yearRange ? ' ('.$yearRange.')' : '')
            : null;

        $vehicleLabel = $vehicle !== null
            ? trim(implode(' ', array_filter([
                $modelYear,
                $vehicle->make,
                $vehicle->model,
            ])))
            : null;

        return [
            'car_id_code' => $carIdCode,
            'selected_application' => $applicationLabel,
            'vehicle_label' => $vehicleLabel !== '' ? $vehicleLabel : null,
            'vehicle_engine_label' => $engineProfile->primaryEngineLabel(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchJobs(
        string $carIdCode,
        ?int $modelYear = null,
        ?string $term = null,
        int $limit = 40,
        ?RteLaborVehicleEngineProfile $engineProfile = null,
    ): array {
        $vehicleMeta = $this->vehicleMeta($carIdCode);

        $query = $this->baseLaborQuery($carIdCode, $modelYear)
            ->select(self::LAB_SELECT_COLUMNS)
            ->orderBy('l.lab_id');

        $this->applyYearFilter($query, $modelYear);

        if (filled($term)) {
            $like = '%'.strtoupper(trim($term)).'%';
            $jobCodes = $this->jobCodesMatchingSearchTerm($term);

            $query->where(function (Builder $builder) use ($like, $jobCodes): void {
                $builder->where('l.lab_id', 'like', $like);

                foreach ($jobCodes as $jobCode) {
                    $builder->orWhere('l.lab_id', 'like', $jobCode.'%');
                }
            });
        } else {
            $query->limit($limit);
        }

        if (filled($term)) {
            $query->limit(self::SEARCH_CANDIDATE_LIMIT);
        }

        $rows = $query->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        $rows = $this->filterRowsForVehicle($rows, $carIdCode, $modelYear);
        $rows = array_map(
            fn (array $row): array => $this->formatJobRow($row, $vehicleMeta, $modelYear),
            $rows,
        );

        $rows = $this->attachJobDescriptions($rows);
        $rows = $this->variantLabels->attach($rows, $carIdCode, $modelYear, $engineProfile);
        $rows = $this->attachIncludedAddOns($rows, $carIdCode, $modelYear);

        if (filled($term)) {
            $rows = $this->rankSearchResults($rows, $term);
            $rows = array_slice($rows, 0, $limit);
        } else {
            $rows = $this->rankRowsByVehicleMatch($rows);
            $rows = array_slice($rows, 0, $limit);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function laborByLabId(string $labId, string $carIdCode, ?int $modelYear = null): ?array
    {
        $vehicleMeta = $this->vehicleMeta($carIdCode);

        $query = $this->baseLaborQuery($carIdCode, $modelYear)
            ->where('l.lab_id', $labId)
            ->select(self::LAB_SELECT_COLUMNS)
            ->limit(1);

        $this->applyYearFilter($query, $modelYear);

        $row = $query->first();

        if ($row === null) {
            return null;
        }

        $formatted = $this->formatJobRow((array) $row, $vehicleMeta, $modelYear);

        $rows = $this->attachJobDescriptions([$formatted]);
        $rows = $this->variantLabels->attach($rows, $carIdCode, $modelYear);
        $rows = $this->attachIncludedAddOns($rows, $carIdCode, $modelYear);

        return $rows[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function hoursWithIncludedAddOns(array $row, RteLaborHoursBasis $basis): ?float
    {
        $base = $this->hoursForBasis($row, $basis);

        if ($base === null) {
            return null;
        }

        $includedTotal = 0.0;

        foreach ($row['included_add_ons'] ?? [] as $addOn) {
            $hours = $addOn[$basis->value.'_hr'] ?? $addOn['avg_hr'] ?? null;

            if ($hours !== null) {
                $includedTotal += (float) $hours;
            }
        }

        return round($base + $includedTotal, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachIncludedAddOns(array $rows, string $carIdCode, ?int $modelYear): array
    {
        foreach ($rows as &$row) {
            $related = $this->relatedOperationsForRow($row, $carIdCode, $modelYear);
            $partitioned = $this->relatedOperationDoctrine->partition($related);
            $bundled = array_map(
                function (array $item): array {
                    $item['book_lo_hr'] = $item['lo_hr'] ?? null;
                    $item['book_avg_hr'] = $item['avg_hr'] ?? null;
                    $item['book_hi_hr'] = $item['hi_hr'] ?? null;

                    return $this->shopHours->applyToRow($item);
                },
                $this->addOns->forLabRow($row),
            );
            $included = [...$partitioned['repair_related'], ...$bundled];
            $optionalDiagnostic = $partitioned['optional_diagnostic'];

            $row['included_add_ons'] = $included;
            $row['optional_diagnostic_operations'] = $optionalDiagnostic;
            $row['included_add_ons_total'] = [
                'lo' => round(array_sum(array_column($included, 'lo_hr')), 2),
                'avg' => round(array_sum(array_column($included, 'avg_hr')), 2),
                'hi' => round(array_sum(array_column($included, 'hi_hr')), 2),
            ];

            foreach (RteLaborHoursBasis::cases() as $basis) {
                $column = 'total_'.$basis->value.'_hr';
                $row[$column] = $this->hoursWithIncludedAddOns($row, $basis);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array{
     *     kind: string,
     *     add_id: null,
     *     lab_id: string,
     *     job_id_code: string,
     *     description: string,
     *     lo_hr: float,
     *     avg_hr: float,
     *     hi_hr: float
     * }>
     */
    private function relatedOperationsForRow(array $row, string $carIdCode, ?int $modelYear): array
    {
        $related = [];

        foreach ($this->relatedOperations->relatedJobCodesForLabRow($row) as $jobIdCode) {
            $match = $this->bestLaborRowForJobCode($jobIdCode, $carIdCode, $modelYear, $row);

            if ($match === null) {
                continue;
            }

            $lo = $this->hoursForBasis($match, RteLaborHoursBasis::Lo);
            $avg = $this->hoursForBasis($match, RteLaborHoursBasis::Avg);
            $hi = $this->hoursForBasis($match, RteLaborHoursBasis::Hi);

            if ($avg === null || $avg <= 0) {
                continue;
            }

            $related[] = [
                'kind' => 'related_operation',
                'add_id' => null,
                'lab_id' => (string) $match['lab_id'],
                'job_id_code' => $jobIdCode,
                'description' => filled($match['job_desc'] ?? null)
                    ? trim((string) $match['job_desc'])
                    : $jobIdCode,
                'book_lo_hr' => $match['book_lo_hr'] ?? null,
                'book_avg_hr' => $match['book_avg_hr'] ?? null,
                'book_hi_hr' => $match['book_hi_hr'] ?? null,
                'lo_hr' => $lo ?? $avg,
                'avg_hr' => $avg,
                'hi_hr' => $hi ?? $avg,
            ];
        }

        return $related;
    }

    /**
     * @param  array<string, mixed>|null  $parentRow
     * @return array<string, mixed>|null
     */
    public function bestLaborRowForJobCode(
        string $jobIdCode,
        string $carIdCode,
        ?int $modelYear = null,
        ?array $parentRow = null,
    ): ?array {
        $jobIdCode = strtoupper(trim($jobIdCode));
        $vehicleMeta = $this->vehicleMeta($carIdCode);

        $query = $this->baseLaborQuery($carIdCode, $modelYear)
            ->where('l.lab_id', 'like', $jobIdCode.'%')
            ->select(self::LAB_SELECT_COLUMNS);

        $this->applyYearFilter($query, $modelYear);

        $rows = $query->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        $rows = $this->filterRowsForVehicle($rows, $carIdCode, $modelYear);

        if ($rows === []) {
            return null;
        }

        $rows = array_map(
            fn (array $match): array => $this->formatJobRow($match, $vehicleMeta, $modelYear),
            $rows,
        );
        $rows = $this->attachJobDescriptions($rows);
        $rows = $this->variantLabels->attach($rows, $carIdCode, $modelYear);

        usort($rows, fn (array $left, array $right): int => $this->relatedOperationRank($right, $parentRow, $carIdCode)
            <=> $this->relatedOperationRank($left, $parentRow, $carIdCode));

        return $rows[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $parentRow
     */
    private function relatedOperationRank(array $row, ?array $parentRow, string $carIdCode): int
    {
        $score = (int) ($row['match_rank'] ?? 0) * 10;

        if ($parentRow !== null) {
            $parentSegment = RteLabVehicleSegment::fromLabId((string) ($parentRow['lab_id'] ?? ''));
            $segment = (string) ($row['vehicle_segment'] ?? RteLabVehicleSegment::fromLabId((string) ($row['lab_id'] ?? '')) ?? '');

            if ($parentSegment !== null && $segment === $parentSegment) {
                $score += 100;
            }

            foreach (['eng1', 'eng2', 'eng3', 'eng4', 'eng5', 'eng6', 'eng7', 'eng8', 'eng9'] as $column) {
                $parentPattern = strtoupper(trim((string) ($parentRow[$column] ?? '')));

                if ($parentPattern === '') {
                    continue;
                }

                $rowPattern = strtoupper(trim((string) ($row[$column] ?? '')));

                if ($rowPattern !== '' && $this->enginePatternMatches($rowPattern, $parentPattern)) {
                    $score += 40;
                    break;
                }
            }
        }

        $avg = $this->hoursForBasis($row, RteLaborHoursBasis::Avg);

        if ($avg !== null && $avg > 0) {
            $score += (int) round($avg * 100);
        }

        return $score;
    }

    public function hoursForBasis(array $row, RteLaborHoursBasis $basis): ?float
    {
        $column = $basis->column();
        $value = $row[$column] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function laborForVehicle(string $carIdCode, ?int $modelYear = null, int $limit = 25): array
    {
        return $this->searchJobs($carIdCode, $modelYear, null, $limit);
    }

    /**
     * @return array{passed: bool, message: string, sample: array<string, mixed>|null}
     */
    public function smokeTest(string $carIdCode = 'DTGB', int $modelYear = 2010): array
    {
        $vehicleCount = (int) DB::table('rte_carlvl3')->where('car_id_code', $carIdCode)->count();
        $labCount = (int) DB::table('rte_lab')->count();

        if ($vehicleCount === 0 || $labCount === 0) {
            return [
                'passed' => false,
                'message' => 'RTE tables are empty or vehicle code is missing.',
                'sample' => null,
            ];
        }

        $rows = $this->searchJobs($carIdCode, $modelYear, null, 1);
        $sample = $rows[0] ?? null;

        if ($sample === null) {
            return [
                'passed' => false,
                'message' => "No joined labor rows for {$carIdCode} ({$modelYear}).",
                'sample' => null,
            ];
        }

        if ($sample['avg_hr'] === null) {
            return [
                'passed' => false,
                'message' => 'Joined row is missing decoded labor hours.',
                'sample' => $sample,
            ];
        }

        return [
            'passed' => true,
            'message' => sprintf(
                '%s %s — %s · avg %.2f hr (lo %.2f / hi %.2f)',
                $sample['car_desc'],
                $modelYear,
                $sample['job_desc'] ?? $sample['lab_id'],
                (float) $sample['avg_hr'],
                (float) ($sample['lo_hr'] ?? 0),
                (float) ($sample['hi_hr'] ?? 0),
            ),
            'sample' => $sample,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function rowCounts(): array
    {
        return collect(RteImportManifest::tableNames())
            ->mapWithKeys(fn (string $table): array => [$table => (int) DB::table($table)->count()])
            ->all();
    }

    /**
     * @return Collection<int, object>
     */
    public function searchVehicles(string $term, int $limit = 20): Collection
    {
        $like = '%'.strtoupper(trim($term)).'%';

        return DB::table('rte_carlvl3')
            ->where('car_desc', 'like', $like)
            ->orderBy('car_desc')
            ->limit($limit)
            ->get(['car_id_code', 'car_desc', 'lo_yr', 'hi_yr']);
    }

    private function baseLaborQuery(string $carIdCode, ?int $modelYear = null): Builder
    {
        $segments = RteLabVehicleSegment::segmentValuesForCar($carIdCode);
        $enginePatterns = $this->enginePatternsForVehicle($carIdCode, $modelYear);

        return DB::table('rte_lab as l')
            ->where(function (Builder $builder) use ($carIdCode, $segments, $enginePatterns): void {
                $builder->where(function (Builder $vehicleMatch) use ($carIdCode, $segments): void {
                    if ($this->hasVehicleSegmentColumn()) {
                        $vehicleMatch->whereIn('l.vehicle_segment', $segments);
                    }

                    $vehicleMatch
                        ->orWhere('l.model1', $carIdCode)
                        ->orWhere('l.model2', $carIdCode)
                        ->orWhere('l.model3', $carIdCode)
                        ->orWhere('l.lab_id', 'like', '%'.$carIdCode.'%');

                    if (! $this->hasVehicleSegmentColumn()) {
                        foreach (RteLabVehicleSegment::labIdPatternsForCar($carIdCode) as $pattern) {
                            $vehicleMatch->orWhere('l.lab_id', 'like', $pattern);
                        }
                    }
                });

                if ($enginePatterns !== []) {
                    $builder->orWhere(function (Builder $engineMatch) use ($enginePatterns): void {
                        foreach (['eng1', 'eng2', 'eng3', 'eng4', 'eng5', 'eng6', 'eng7', 'eng8', 'eng9'] as $column) {
                            foreach ($enginePatterns as $pattern) {
                                $engineMatch->orWhere('l.'.$column, 'like', $pattern);
                            }
                        }
                    });
                }
            });
    }

    private function hasVehicleSegmentColumn(): bool
    {
        return self::$hasVehicleSegmentColumn ??= Schema::hasColumn('rte_lab', 'vehicle_segment');
    }

    private function applyYearFilter(Builder $query, ?int $modelYear): void
    {
        if ($modelYear === null) {
            return;
        }

        $year = str_pad((string) $modelYear, 4, '0', STR_PAD_LEFT);

        $query->where(function (Builder $builder) use ($year): void {
            $builder
                ->where(function (Builder $openEnded): void {
                    $openEnded
                        ->where('l.lo_yr', '0000')
                        ->orWhere('l.hi_yr', '9999');
                })
                ->orWhere(function (Builder $bounded) use ($year): void {
                    $bounded
                        ->where('l.lo_yr', '<=', $year)
                        ->where('l.hi_yr', '>=', $year);
                });
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{car_id_code: string, car_desc: string|null}  $vehicleMeta
     * @return array<string, mixed>
     */
    private function formatJobRow(array $row, array $vehicleMeta, ?int $modelYear = null): array
    {
        foreach (['hi_hr', 'avg_hr', 'lo_hr'] as $column) {
            if (isset($row[$column]) && $row[$column] !== null && $row[$column] !== '') {
                $row[$column] = round((float) $row[$column], 2);
            }
        }

        $row['car_id_code'] = $vehicleMeta['car_id_code'];
        $row['car_desc'] = $vehicleMeta['car_desc'];
        $row['job_desc'] = filled($row['job_desc'] ?? null)
            ? trim((string) $row['job_desc'])
            : null;

        $row['book_lo_hr'] = $row['lo_hr'] ?? null;
        $row['book_avg_hr'] = $row['avg_hr'] ?? null;
        $row['book_hi_hr'] = $row['hi_hr'] ?? null;

        return $this->shopHours->applyToRow($row);
    }

    public function vehicleAgeMultiplier(?int $modelYear): float
    {
        return $this->shopHours->vehicleAgeMultiplier($modelYear);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function applyVehicleAgePaddingToRow(array $row, ?int $modelYear, bool $apply): array
    {
        if (! $apply) {
            return $row;
        }

        $multiplier = $this->shopHours->vehicleAgeMultiplier($modelYear);

        if ($multiplier <= 1.0) {
            return $row;
        }

        $row = [
            ...$row,
            ...$this->shopHours->applyAgePaddingToHours([
                'lo_hr' => $row['lo_hr'] ?? null,
                'avg_hr' => $row['avg_hr'] ?? null,
                'hi_hr' => $row['hi_hr'] ?? null,
            ], $multiplier),
        ];

        if (! isset($row['included_add_ons']) || ! is_array($row['included_add_ons'])) {
            return $row;
        }

        $included = $this->applyAgePaddingToAddOnRows($row['included_add_ons'], $multiplier);
        $optionalDiagnostic = isset($row['optional_diagnostic_operations']) && is_array($row['optional_diagnostic_operations'])
            ? $this->applyAgePaddingToAddOnRows($row['optional_diagnostic_operations'], $multiplier)
            : [];

        $row['included_add_ons'] = $included;
        $row['optional_diagnostic_operations'] = $optionalDiagnostic;
        $row['included_add_ons_total'] = [
            'lo' => round(array_sum(array_column($included, 'lo_hr')), 2),
            'avg' => round(array_sum(array_column($included, 'avg_hr')), 2),
            'hi' => round(array_sum(array_column($included, 'hi_hr')), 2),
        ];

        foreach (RteLaborHoursBasis::cases() as $basis) {
            $row['total_'.$basis->value.'_hr'] = $this->hoursWithIncludedAddOns($row, $basis);
        }

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyAgePaddingToAddOnRows(array $rows, float $multiplier): array
    {
        return array_map(
            fn (array $addOn): array => [
                ...$addOn,
                ...$this->shopHours->applyAgePaddingToHours([
                    'lo_hr' => $addOn['lo_hr'] ?? null,
                    'avg_hr' => $addOn['avg_hr'] ?? null,
                    'hi_hr' => $addOn['hi_hr'] ?? null,
                ], $multiplier),
            ],
            $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    public function applyVehicleAgePaddingToSuggestedPackage(array $package, ?int $modelYear, bool $apply): array
    {
        if (! $apply) {
            return $package;
        }

        $multiplier = $this->shopHours->vehicleAgeMultiplier($modelYear);

        if ($multiplier <= 1.0) {
            return $package;
        }

        $lines = [];

        foreach ($package['lines'] ?? [] as $line) {
            $lines[] = [
                ...$line,
                ...$this->shopHours->applyAgePaddingToHours([
                    'lo_hr' => $line['lo_hr'] ?? null,
                    'avg_hr' => $line['avg_hr'] ?? null,
                    'hi_hr' => $line['hi_hr'] ?? null,
                ], $multiplier),
            ];
        }

        $package['lines'] = $lines;

        $optionalDiagnostics = [];

        foreach ($package['optional_diagnostic_operations'] ?? [] as $optional) {
            $optionalDiagnostics[] = [
                ...$optional,
                ...$this->shopHours->applyAgePaddingToHours([
                    'lo_hr' => $optional['lo_hr'] ?? null,
                    'avg_hr' => $optional['avg_hr'] ?? null,
                    'hi_hr' => $optional['hi_hr'] ?? null,
                ], $multiplier),
            ];
        }

        $package['optional_diagnostic_operations'] = $optionalDiagnostics;

        foreach (RteLaborHoursBasis::cases() as $basis) {
            $column = $basis->value.'_hr';
            $total = 0.0;

            foreach ($lines as $line) {
                $hours = $line[$column] ?? $line['avg_hr'] ?? null;

                if ($hours !== null) {
                    $total += (float) $hours;
                }
            }

            $package['total_'.$basis->value.'_hr'] = round($total, 2);
        }

        return $package;
    }

    /**
     * @return array{car_id_code: string, car_desc: string|null}
     */
    private function vehicleMeta(string $carIdCode): array
    {
        $carDesc = DB::table('rte_carlvl3')
            ->where('car_id_code', $carIdCode)
            ->orderBy('car_desc')
            ->value('car_desc');

        return [
            'car_id_code' => $carIdCode,
            'car_desc' => filled($carDesc) ? trim((string) $carDesc) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachJobDescriptions(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $prefixes = [];

        foreach ($rows as $row) {
            $labId = (string) ($row['lab_id'] ?? '');

            for ($length = 7; $length >= 4; $length--) {
                if (strlen($labId) >= $length) {
                    $prefixes[substr($labId, 0, $length)] = true;
                }
            }
        }

        $descriptions = DB::table('rte_job_lku')
            ->whereIn('job_id_code', array_keys($prefixes))
            ->pluck('job_desc', 'job_id_code');

        foreach ($rows as &$row) {
            if (filled($row['job_desc'] ?? null)) {
                continue;
            }

            $labId = (string) ($row['lab_id'] ?? '');

            for ($length = 7; $length >= 4; $length--) {
                if (strlen($labId) < $length) {
                    continue;
                }

                $code = substr($labId, 0, $length);
                $description = $descriptions->get($code);

                if (filled($description)) {
                    $row['job_desc'] = trim((string) $description);
                    break;
                }
            }
        }
        unset($row);

        usort($rows, fn (array $left, array $right): int => strcmp(
            (string) ($left['job_desc'] ?? $left['lab_id'] ?? ''),
            (string) ($right['job_desc'] ?? $right['lab_id'] ?? ''),
        ));

        $seen = [];

        return array_values(array_filter(
            $rows,
            function (array $row) use (&$seen): bool {
                $labId = (string) ($row['lab_id'] ?? '');

                if ($labId === '' || isset($seen[$labId])) {
                    return false;
                }

                $seen[$labId] = true;

                return true;
            },
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function rankSearchResults(array $rows, string $term): array
    {
        $words = array_values(array_filter(preg_split('/\s+/', strtoupper(trim($term))) ?: []));

        if ($words === []) {
            return $rows;
        }

        usort($rows, function (array $left, array $right) use ($words): int {
            $rankDelta = (int) ($right['match_rank'] ?? 0) <=> (int) ($left['match_rank'] ?? 0);

            if ($rankDelta !== 0) {
                return $rankDelta;
            }

            $scoreDelta = $this->searchRelevanceScore($right, $words) <=> $this->searchRelevanceScore($left, $words);

            if ($scoreDelta !== 0) {
                return $scoreDelta;
            }

            return strcmp(
                (string) ($left['job_desc'] ?? $left['lab_id'] ?? ''),
                (string) ($right['job_desc'] ?? $right['lab_id'] ?? ''),
            );
        });

        return $rows;
    }

    /**
     * @param  list<string>  $words
     */
    private function searchRelevanceScore(array $row, array $words): int
    {
        $description = strtoupper((string) ($row['job_desc'] ?? ''));
        $score = 0;
        $matchedWords = 0;

        foreach ($words as $word) {
            $wordMatched = str_contains($description, $word);

            if ($wordMatched) {
                $score += 20;
            }

            foreach ($this->searchSynonyms($word) as $synonym) {
                if (str_contains($description, $synonym)) {
                    $score += 8;
                    $wordMatched = true;
                }
            }

            if ($wordMatched) {
                $matchedWords++;
            }
        }

        if ($matchedWords >= count($words)) {
            $score += 40;
        }

        if (count($words) === 1 && str_contains($description, $words[0])) {
            $score -= substr_count($description, ' ');
        }

        if (count($words) <= 2 && str_contains($description, '&')) {
            $score -= 12;
        }

        $queryText = implode(' ', $words);

        if (str_contains($queryText, 'BRAKE') && ! str_contains($queryText, 'ROTOR')) {
            if (str_contains($description, 'ROTOR') || str_contains($description, 'REPLACE')) {
                $score -= 30;
            }
        }

        return $score;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function rankRowsByVehicleMatch(array $rows): array
    {
        usort($rows, fn (array $left, array $right): int => ((int) ($right['match_rank'] ?? 0)) <=> ((int) ($left['match_rank'] ?? 0)));

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterRowsForVehicle(array $rows, string $carIdCode, ?int $modelYear): array
    {
        $enginePatterns = $this->enginePatternsForVehicle($carIdCode, $modelYear);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->rowMatchesVehicle($row, $carIdCode, $enginePatterns),
        ));
    }

    /**
     * @param  list<string>  $enginePatterns
     */
    private function rowMatchesVehicle(array $row, string $carIdCode, array $enginePatterns): bool
    {
        foreach (['model1', 'model2', 'model3'] as $column) {
            if (($row[$column] ?? null) === $carIdCode) {
                return true;
            }
        }

        $labId = (string) ($row['lab_id'] ?? '');

        if (str_contains($labId, $carIdCode)) {
            return true;
        }

        $segment = (string) ($row['vehicle_segment'] ?? RteLabVehicleSegment::fromLabId($labId) ?? '');

        if ($segment !== '' && RteLabVehicleSegment::matchesCar($segment, $carIdCode)) {
            return true;
        }

        if ($segment !== '' && strpbrk($segment, 'xX') === false && $segment !== strtoupper($carIdCode)) {
            return false;
        }

        return $this->rowMatchesEngine($row, $enginePatterns);
    }

    /**
     * @param  list<string>  $enginePatterns
     */
    private function rowMatchesEngine(array $row, array $enginePatterns): bool
    {
        if ($enginePatterns === []) {
            return false;
        }

        foreach (['eng1', 'eng2', 'eng3', 'eng4', 'eng5', 'eng6', 'eng7', 'eng8', 'eng9'] as $column) {
            $value = strtoupper(trim((string) ($row[$column] ?? '')));

            if ($value === '') {
                continue;
            }

            foreach ($enginePatterns as $pattern) {
                if ($this->enginePatternMatches($value, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function enginePatternMatches(string $value, string $pattern): bool
    {
        $regex = '/^'.str_replace(['%', 'xxx', 'XXX'], ['.*', '.*', '.*'], preg_quote($pattern, '/')).'$/i';

        return preg_match($regex, $value) === 1;
    }

    /**
     * @return list<string>
     */
    private function enginePatternsForVehicle(string $carIdCode, ?int $modelYear): array
    {
        $query = DB::table('rte_engtbl')->where('mod_id_code', $carIdCode);

        if ($modelYear !== null) {
            $year = str_pad((string) $modelYear, 4, '0', STR_PAD_LEFT);

            $query
                ->where('lo_yr', '<=', $year)
                ->where('hi_yr', '>=', $year);
        }

        $patterns = [];

        foreach ($query->pluck('eng_id_code') as $engineCode) {
            $engineCode = strtoupper((string) $engineCode);

            if ($engineCode === '') {
                continue;
            }

            $patterns[$engineCode] = true;

            if (strlen($engineCode) >= 3) {
                $patterns[substr($engineCode, 0, 3).'%'] = true;
                $patterns[substr($engineCode, 0, 3).'xxx'] = true;
            }
        }

        return array_keys($patterns);
    }

    /**
     * @return list<string>
     */
    private function jobCodesMatchingSearchTerm(string $term): array
    {
        $words = array_values(array_filter(preg_split('/\s+/', strtoupper(trim($term))) ?: []));

        if ($words === []) {
            return [];
        }

        $query = DB::table('rte_job_lku');

        foreach ($words as $word) {
            $query->where(function (Builder $builder) use ($word): void {
                $builder->where('job_desc', 'like', '%'.$word.'%');

                foreach ($this->searchSynonyms($word) as $synonym) {
                    $builder->orWhere('job_desc', 'like', '%'.$synonym.'%');
                }
            });
        }

        return $query
            ->orderBy('job_id_code')
            ->limit(40)
            ->pluck('job_id_code')
            ->map(fn (mixed $code): string => (string) $code)
            ->all();
    }

    /**
     * @return list<string>
     */
    private function searchSynonyms(string $word): array
    {
        return match ($word) {
            'BRAKE', 'BRAKES' => ['DISC', 'PAD', 'PADS', 'ROTOR', 'ROTORS', 'DRUM', 'SHOE', 'FRONT'],
            'RADIATOR' => ['RADIATOR', 'COOLING'],
            'AC', 'A/C' => ['A-C', 'AIR CONDITION'],
            default => [],
        };
    }
}
