<?php

namespace App\Ark\Growth\Projections;

use App\Ark\Growth\Models\GrowthContent;

final class GrowthRevenueHeatmapProjection
{
    public const COLS = 26;

    public const ROWS = 16;

    /**
     * @return array{
     *     cols: int,
     *     rows: int,
     *     cells: list<array{index: int, path: string|null, title: string|null, revenue_cents: int, tier: string}>
     * }
     */
    public function resolve(): array
    {
        $contents = GrowthContent::query()
            ->orderByDesc('revenue_cents')
            ->orderByDesc('priority')
            ->limit(self::COLS * self::ROWS)
            ->get();

        $maxRevenue = max(1, (int) $contents->max('revenue_cents'));
        $cells = [];

        for ($index = 0; $index < self::COLS * self::ROWS; $index++) {
            $content = $contents->get($index);

            if ($content === null) {
                $cells[] = [
                    'index' => $index,
                    'path' => null,
                    'title' => null,
                    'revenue_cents' => 0,
                    'tier' => 'empty',
                ];

                continue;
            }

            $ratio = $content->revenue_cents / $maxRevenue;

            $cells[] = [
                'index' => $index,
                'path' => $content->path,
                'title' => $content->title,
                'revenue_cents' => $content->revenue_cents,
                'tier' => match (true) {
                    $content->revenue_cents === 0 => 'idle',
                    $ratio >= 0.75 => 'hot',
                    $ratio >= 0.4 => 'warm',
                    $ratio >= 0.15 => 'cool',
                    default => 'trace',
                },
            ];
        }

        return [
            'cols' => self::COLS,
            'rows' => self::ROWS,
            'cells' => $cells,
        ];
    }
}
