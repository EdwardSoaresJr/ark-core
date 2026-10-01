<?php

namespace App\Ark\Dragon\Agent;

final class RecallDragonMemory
{
    public const LIMIT = 12;

    /**
     * @return list<array<string, mixed>>
     */
    public function facts(?string $needle = null, ?DragonMemoryContext $context = null): array
    {
        $context ??= app(DragonMemoryContext::class);
        $needle = trim((string) $needle);
        $query = DragonAgentMemory::query()->whereNull('superseded_at');

        $query->where(function ($outer) use ($context): void {
            $outer->where(function ($company): void {
                $company->where('scope_type', 'company')
                    ->whereNull('workstation_id')
                    ->whereNull('user_id');
            });
            if ($context->workstation !== null) {
                $outer->orWhere(function ($station) use ($context): void {
                    $station->where('scope_type', 'workstation')
                        ->where('workstation_id', $context->workstation->id)
                        ->whereNull('user_id');
                });
            }
            if ($context->user !== null) {
                $outer->orWhere(function ($user) use ($context): void {
                    $user->where('scope_type', 'user')
                        ->where('user_id', $context->user->id);
                });
            }
        });

        $rows = $query->orderByDesc('id')->limit(80)->get();
        $scored = [];
        foreach ($rows as $row) {
            $score = $this->score($row, $needle);
            if ($needle !== '' && $score < 1) {
                continue;
            }
            $scored[] = ['row' => $row, 'score' => $score];
        }

        usort($scored, function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                $rank = ['workstation' => 3, 'user' => 2, 'company' => 1];
                $ar = $rank[$a['row']->scope_type] ?? 0;
                $br = $rank[$b['row']->scope_type] ?? 0;
                if ($ar !== $br) {
                    return $br <=> $ar;
                }

                return $b['row']->id <=> $a['row']->id;
            }

            return $b['score'] <=> $a['score'];
        });

        $out = [];
        foreach (array_slice($scored, 0, self::LIMIT) as $item) {
            /** @var DragonAgentMemory $row */
            $row = $item['row'];
            $out[] = [
                'id' => $row->id,
                'key' => $row->fact_key,
                'value' => $row->fact_value,
                'scope' => $row->scope_type,
                'workstation_id' => $row->workstation_id,
                'user_id' => $row->user_id,
                'category' => $row->category,
                'taught_by' => $row->taught_by,
                'provenance' => $row->provenance,
            ];
        }

        return $out;
    }

    private function score(DragonAgentMemory $row, string $needle): int
    {
        if ($needle === '') {
            return match ($row->scope_type) {
                'workstation' => 3,
                'user' => 2,
                default => 1,
            };
        }

        $hay = mb_strtolower($row->fact_key.' '.$row->fact_value);
        $score = 0;
        foreach (preg_split('/\s+/', mb_strtolower($needle)) ?: [] as $term) {
            $term = trim($term, ".,!?'\"");
            if (mb_strlen($term) < 3) {
                continue;
            }
            if (str_contains($hay, $term)) {
                $score += mb_strlen($term) > 6 ? 3 : 2;
            }
        }

        if (str_contains($hay, mb_strtolower($needle))) {
            $score += 8;
        }

        return $score;
    }
}
