<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\Labor\LaborEngineMatchSource;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maps the RO vehicle to RTE engtbl rows for scoring and readable labels.
 */
final class RteLaborVehicleEngineProfile
{
    /** @var Collection<int, object{eng_id_code: string, eng_desc: string, lo_yr: string, hi_yr: string}> */
    private Collection $engines;

    private readonly ?object $primaryEngine;

    public function __construct(
        private readonly ?string $primaryEngineLabel = null,
        Collection $engines = new Collection,
        ?object $primaryEngine = null,
        private readonly LaborEngineMatchSource $matchSource = LaborEngineMatchSource::AssumedDefault,
        private readonly int $matchScore = 0,
    ) {
        $this->engines = $engines->values();
        $this->primaryEngine = $primaryEngine;
    }

    public static function forVehicle(
        ?Vehicle $vehicle,
        string $carIdCode,
        ?int $modelYear,
        ?string $selectedEngIdCode = null,
    ): self {
        $carIdCode = strtoupper(trim($carIdCode));

        if ($carIdCode === '') {
            return new self(engines: collect());
        }

        $query = DB::table('rte_engtbl')->where('mod_id_code', $carIdCode);

        if ($modelYear !== null) {
            $year = str_pad((string) $modelYear, 4, '0', STR_PAD_LEFT);

            $query
                ->where('lo_yr', '<=', $year)
                ->where('hi_yr', '>=', $year);
        }

        $engines = $query
            ->orderBy('eng_desc')
            ->get(['eng_id_code', 'eng_desc', 'lo_yr', 'hi_yr']);

        $profile = new self(engines: $engines);

        if ($selectedEngIdCode !== null && $selectedEngIdCode !== '') {
            $selected = $profile->findEngine($selectedEngIdCode);

            if ($selected !== null) {
                $label = (new RteEngineDescriptionFormatter)->format((string) $selected->eng_desc);

                return new self(
                    $label,
                    $engines,
                    $selected,
                    LaborEngineMatchSource::AdvisorSelected,
                    120,
                );
            }
        }

        $resolved = $profile->resolvePrimaryEngine($vehicle, $engines);
        $primaryEngine = $resolved['engine'];
        $label = $primaryEngine !== null
            ? (new RteEngineDescriptionFormatter)->format((string) $primaryEngine->eng_desc)
            : $profile->labelFromVehicleFields($vehicle);

        return new self(
            $label,
            $engines,
            $primaryEngine,
            $resolved['source'],
            $resolved['score'],
        );
    }

    public function primaryEngineLabel(): ?string
    {
        return filled($this->primaryEngineLabel) ? $this->primaryEngineLabel : null;
    }

    public function selectedEngIdCode(): ?string
    {
        $code = strtoupper(trim((string) ($this->primaryEngine?->eng_id_code ?? '')));

        return $code !== '' ? $code : null;
    }

    public function matchSource(): LaborEngineMatchSource
    {
        return $this->matchSource;
    }

    public function matchScore(): int
    {
        return $this->matchScore;
    }

    public function engineSelectionRequired(?Vehicle $vehicle, ?string $selectedEngIdCode): bool
    {
        if ($selectedEngIdCode !== null && $selectedEngIdCode !== '') {
            return false;
        }

        if ($this->engines->count() <= 1) {
            return false;
        }

        if ($vehicle === null || ! $vehicle->hasEngineOnRecord()) {
            return true;
        }

        return $this->matchSource === LaborEngineMatchSource::AssumedDefault;
    }

    /**
     * @return list<array{eng_id_code: string, label: string, eng_desc: string}>
     */
    public function engineOptions(): array
    {
        $formatter = new RteEngineDescriptionFormatter;
        $selectedCode = strtoupper(trim((string) ($this->primaryEngine?->eng_id_code ?? '')));

        return $this->engines
            ->map(function (object $engine) use ($formatter, $selectedCode): array {
                $code = strtoupper(trim((string) $engine->eng_id_code));

                return [
                    'eng_id_code' => $code,
                    'label' => $formatter->format((string) $engine->eng_desc),
                    'eng_desc' => (string) $engine->eng_desc,
                    'is_selected' => $code !== '' && $code === $selectedCode,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function engineCodes(): array
    {
        return $this->engines
            ->pluck('eng_id_code')
            ->map(fn ($code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function engineMatchScore(array $row): int
    {
        $primaryCode = strtoupper(trim((string) ($this->primaryEngine?->eng_id_code ?? '')));
        $best = 0;

        foreach (['eng1', 'eng2', 'eng3', 'eng4', 'eng5', 'eng6', 'eng7', 'eng8', 'eng9'] as $column) {
            $pattern = strtoupper(trim((string) ($row[$column] ?? '')));

            if ($pattern === '') {
                continue;
            }

            foreach ($this->engines as $engine) {
                $engineCode = strtoupper(trim((string) $engine->eng_id_code));

                if (! $this->enginePatternMatches($engineCode, $pattern)) {
                    continue;
                }

                if ($primaryCode !== '' && $engineCode === $primaryCode) {
                    $best = max($best, 120);

                    continue;
                }

                if ($this->containsWildcard($pattern)) {
                    $best = max($best, 60);
                } else {
                    $best = max($best, 90);
                }
            }
        }

        return $best;
    }

    public function matchesEnginePattern(string $pattern): bool
    {
        $pattern = strtoupper(trim($pattern));

        if ($pattern === '') {
            return false;
        }

        foreach ($this->engines as $engine) {
            $engineCode = strtoupper(trim((string) $engine->eng_id_code));

            if ($this->enginePatternMatches($engineCode, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function preferredEngineForPattern(string $pattern): ?object
    {
        $pattern = strtoupper(trim($pattern));

        if ($pattern === '' || $this->primaryEngine === null) {
            return null;
        }

        $primaryCode = strtoupper(trim((string) $this->primaryEngine->eng_id_code));

        if ($this->enginePatternMatches($primaryCode, $pattern)) {
            return $this->primaryEngine;
        }

        return null;
    }

    /**
     * @param  Collection<int, object{eng_id_code: string, eng_desc: string, lo_yr: string, hi_yr: string}>  $engines
     * @return array{engine: ?object, score: int, source: LaborEngineMatchSource}
     */
    private function resolvePrimaryEngine(?Vehicle $vehicle, Collection $engines): array
    {
        if ($engines->isEmpty()) {
            return [
                'engine' => null,
                'score' => 0,
                'source' => LaborEngineMatchSource::AssumedDefault,
            ];
        }

        if ($vehicle === null) {
            return [
                'engine' => $engines->first(),
                'score' => 0,
                'source' => LaborEngineMatchSource::AssumedDefault,
            ];
        }

        $displacement = $vehicle->displacement_liters !== null
            ? round((float) $vehicle->displacement_liters, 1)
            : null;

        $engineText = strtoupper(trim(implode(' ', array_filter([
            (string) ($vehicle->engine_display ?? ''),
            (string) ($vehicle->engine ?? ''),
            (string) ($vehicle->engine_code ?? ''),
        ]))));

        $best = null;
        $bestScore = 0;

        foreach ($engines as $engine) {
            $score = 0;
            $desc = strtoupper((string) $engine->eng_desc);

            if ($displacement !== null && preg_match('/\b'.preg_quote((string) $displacement, '/').'\s*L\b/i', $desc) === 1) {
                $score += 100;
            }

            if ($engineText !== '') {
                if ($displacement !== null && str_contains($engineText, (string) $displacement)) {
                    $score += 40;
                }

                foreach (['HEMI', 'CUMMINS', 'POWERSTROKE', 'DURAMAX', 'V8', 'V6', 'TURBO', 'DIESEL'] as $token) {
                    if (str_contains($engineText, $token) && str_contains($desc, $token)) {
                        $score += 20;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $engine;
            }
        }

        if ($bestScore > 0) {
            return [
                'engine' => $best,
                'score' => $bestScore,
                'source' => LaborEngineMatchSource::VehicleRecord,
            ];
        }

        return [
            'engine' => $engines->first(),
            'score' => 0,
            'source' => LaborEngineMatchSource::AssumedDefault,
        ];
    }

    private function findEngine(string $engIdCode): ?object
    {
        $engIdCode = strtoupper(trim($engIdCode));

        if ($engIdCode === '') {
            return null;
        }

        return $this->engines->first(
            fn (object $engine): bool => strtoupper(trim((string) $engine->eng_id_code)) === $engIdCode,
        );
    }

    private function labelFromVehicleFields(?Vehicle $vehicle): ?string
    {
        if ($vehicle === null) {
            return null;
        }

        if (filled($vehicle->engine_display)) {
            return trim((string) $vehicle->engine_display);
        }

        $parts = array_filter([
            $vehicle->displacement_liters !== null
                ? rtrim(rtrim(number_format((float) $vehicle->displacement_liters, 1, '.', ''), '0'), '.').'L'
                : null,
            filled($vehicle->engine) ? trim((string) $vehicle->engine) : null,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function enginePatternMatches(string $engineCode, string $pattern): bool
    {
        return (new RteEnginePatternMatcher)->matches($engineCode, $pattern);
    }

    private function containsWildcard(string $pattern): bool
    {
        return strpbrk($pattern, 'xX') !== false;
    }
}
