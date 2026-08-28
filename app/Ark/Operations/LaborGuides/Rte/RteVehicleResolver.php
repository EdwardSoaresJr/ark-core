<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RteVehicleResolver
{
    /**
     * @return Collection<int, object{car_id_code: string, car_desc: string, lo_yr: string, hi_yr: string, lvl123_code: string}>
     */
    public function candidates(?int $year, ?string $make, ?string $model, int $limit = 25): Collection
    {
        if ($year === null || ! filled($model)) {
            return collect();
        }

        $cacheKey = 'rte:vehicle:v3:'.sha1(implode('|', [
            (string) $year,
            Str::upper(trim((string) $make)),
            Str::upper(trim((string) $model)),
            (string) $limit,
        ]));

        /** @var list<array{lvl123_code: string, car_id_code: string, car_desc: string, lo_yr: string, hi_yr: string}> $cached */
        $cached = Cache::remember($cacheKey, now()->addHour(), fn (): array => $this->resolveCandidates($year, $make, $model, $limit)
            ->map(fn (object $row): array => [
                'lvl123_code' => (string) $row->lvl123_code,
                'car_id_code' => (string) $row->car_id_code,
                'car_desc' => (string) $row->car_desc,
                'lo_yr' => (string) $row->lo_yr,
                'hi_yr' => (string) $row->hi_yr,
            ])
            ->values()
            ->all());

        return collect($cached)->map(fn (array $row): object => (object) $row);
    }

    /**
     * @return Collection<int, object{car_id_code: string, car_desc: string, lo_yr: string, hi_yr: string, lvl123_code: string}>
     */
    private function resolveCandidates(?int $year, ?string $make, ?string $model, int $limit): Collection
    {
        $yearStr = str_pad((string) $year, 4, '0', STR_PAD_LEFT);
        $searchTerms = $this->searchTerms($make, $model);

        if ($searchTerms === []) {
            return collect();
        }

        $rows = DB::table('rte_carlvl3')
            ->where('lo_yr', '<=', $yearStr)
            ->where('hi_yr', '>=', $yearStr)
            ->where(function ($builder) use ($searchTerms): void {
                foreach ($searchTerms as $term) {
                    $builder->orWhere('car_desc', 'like', '%'.$term.'%');
                }
            })
            ->get([
                'lvl123_code',
                'car_id_code',
                'car_desc',
                'lo_yr',
                'hi_yr',
            ]);

        return $this->rankCandidates($rows, $year, $make, $model)
            ->unique(fn (object $row): string => (string) $row->car_id_code)
            ->take($limit)
            ->values();
    }

    public function bestCarIdCode(?int $year, ?string $make, ?string $model): ?string
    {
        return $this->candidates($year, $make, $model, 1)->first()?->car_id_code;
    }

    /**
     * @return list<string>
     */
    private function searchTerms(?string $make, ?string $model): array
    {
        $model = Str::upper(trim((string) $model));
        $make = Str::upper(trim((string) $make));

        $terms = [];

        if ($model !== '') {
            $compact = preg_replace('/[^A-Z0-9]/', '', $model) ?? $model;
            $terms[] = $compact;
            $terms[] = preg_replace('/[^A-Z0-9 ]/', '', $model) ?? $model;

            foreach ($this->modelSearchVariants($model) as $variant) {
                $terms[] = $variant;
            }

            $firstWord = Str::before($model, ' ');

            if ($firstWord !== '' && $firstWord !== $model) {
                $terms[] = $firstWord;
            }
        }

        if ($make !== '' && ! Str::contains($model, $make)) {
            $terms[] = preg_replace('/[^A-Z0-9 ]/', '', $make) ?? $make;
        }

        foreach ($this->makeAliases($make) as $alias) {
            if (! in_array($alias, $terms, true)) {
                $terms[] = $alias;
            }
        }

        return array_values(array_unique(array_filter(
            $terms,
            fn (string $term): bool => strlen($term) >= 3,
        )));
    }

    /**
     * @return list<string>
     */
    private function modelSearchVariants(string $model): array
    {
        $variants = [];
        $compact = preg_replace('/[^A-Z0-9]/', '', Str::upper(trim($model))) ?? '';

        if ($compact === '') {
            return [];
        }

        if (preg_match('/^(\d)([A-Z].*)$/', $compact, $match) === 1) {
            $variants[] = $match[1].'-'.$match[2];
        }

        if (preg_match('/^4(.+)$/', $compact, $match) === 1 && strlen($match[1]) >= 3) {
            $variants[] = '4-'.$match[1];
            $variants[] = $match[1];
        }

        if (preg_match('/^E(\d)$/', $compact, $match) === 1) {
            $variants[] = 'E-'.$match[1];
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * @return list<string>
     */
    private function makeAliases(?string $make): array
    {
        $make = Str::upper(trim((string) $make));

        return match ($make) {
            'RAM', 'DODGE' => ['PICK-UP', 'PICK UP'],
            'CHEVROLET', 'CHEVY', 'GMC' => ['P-U', 'PICK-UP'],
            default => [],
        };
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function rankCandidates(Collection $rows, int $year, ?string $make, ?string $model): Collection
    {
        return $rows
            ->sort(function (object $left, object $right) use ($year, $make, $model): int {
                $scoreCompare = $this->scoreCandidate($right, $year, $make, $model)
                    <=> $this->scoreCandidate($left, $year, $make, $model);

                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }

                return (int) $right->hi_yr <=> (int) $left->hi_yr;
            })
            ->values();
    }

    private function scoreCandidate(object $row, int $year, ?string $make, ?string $model): int
    {
        $desc = Str::upper(trim((string) $row->car_desc));
        $make = Str::upper(trim((string) $make));
        $model = Str::upper(trim((string) $model));
        $loYear = (int) $row->lo_yr;
        $hiYear = (int) $row->hi_yr;
        $score = 0;

        if ($hiYear >= $year) {
            $score += $hiYear >= $year + 1 ? 20 : 8;
        }

        $span = max(1, $hiYear - $loYear);
        $score += max(0, 24 - intdiv($span, 8));

        if ($model !== '') {
            if ($this->descriptionMatchesTruckModel($desc, $model)) {
                $score += 90;
            } elseif ($this->descriptionContainsModelToken($desc, $model)) {
                $score += 45;
            }
        }

        if (in_array($make, ['RAM', 'DODGE'], true)) {
            if (preg_match('/\bR\s*2500|\bR\s*3500|\bR\s*1500|\bRAM\b/', $desc) === 1) {
                $score += 35;
            }

            if (str_contains($desc, 'PICK-UP') || str_contains($desc, 'PICK UP')) {
                $score += 25;
            }

            if (str_contains($desc, 'C SERIES') || str_contains($desc, 'C20-C') || str_contains($desc, 'C10-C')) {
                $score -= 70;
            }

            if (str_contains($desc, 'EXPRESS') && str_contains($desc, 'VAN')) {
                $score -= 40;
            }

            if (str_contains($desc, ' NV ')) {
                $score -= 50;
            }
        }

        if (str_starts_with($desc, '*')) {
            $score += 4;
        }

        return $score;
    }

    private function descriptionMatchesTruckModel(string $description, string $model): bool
    {
        if (preg_match('/\b(\d{4})\b/', $model, $match) !== 1) {
            return false;
        }

        $series = $match[1];

        if (! in_array($series, ['1500', '2500', '3500', '4500', '5500'], true)) {
            return false;
        }

        return preg_match('/\bR\s*'.$series.'\b|\b'.$series.'-3500\b|\b'.$series.'\b.*PICK/s', $description) === 1
            && (str_contains($description, 'PICK-UP') || str_contains($description, 'PICK UP') || str_contains($description, 'P-U'));
    }

    private function descriptionContainsModelToken(string $description, string $model): bool
    {
        $descriptionCompact = preg_replace('/[^A-Z0-9]/', '', $description) ?? $description;

        foreach ($this->modelTokens($model) as $token) {
            if (preg_match('/\b'.preg_quote($token, '/').'\b/', $description) === 1) {
                return true;
            }

            if (strlen($token) >= 4 && str_contains($descriptionCompact, $token)) {
                return true;
            }
        }

        $modelCompact = preg_replace('/[^A-Z0-9]/', '', Str::upper($model)) ?? '';

        return $modelCompact !== ''
            && strlen($modelCompact) >= 4
            && str_contains($descriptionCompact, $modelCompact);
    }

    /**
     * @return list<string>
     */
    private function modelTokens(string $model): array
    {
        $normalized = preg_replace('/[^A-Z0-9 ]/', '', Str::upper($model)) ?? Str::upper($model);
        $tokens = array_filter(explode(' ', $normalized));

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token): bool => strlen($token) >= 3,
        )));
    }
}
