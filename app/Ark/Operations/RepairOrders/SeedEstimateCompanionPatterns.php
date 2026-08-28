<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SeedEstimateCompanionPatterns
{
    /**
     * @return list<array{
     *     job_key: string,
     *     job_needles: list<string>,
     *     companion_key: string,
     *     companion_label: string,
     *     companion_needles: list<string>,
     *     support_count: int,
     *     exception_count: int,
     *     source: string,
     * }>
     */
    public static function defaults(): array
    {
        return [
            [
                'job_key' => 'belt|timing',
                'job_needles' => ['timing belt', 'timing chain', 'timing kit', 'cambelt', 'cam belt'],
                'companion_key' => 'oil',
                'companion_label' => 'oil',
                'companion_needles' => ['oil change', 'engine oil', 'motor oil', 'synthetic oil', 'bulk oil', '5w', '0w', '10w'],
                'support_count' => 3,
                'exception_count' => 0,
                'source' => 'seed',
            ],
            [
                'job_key' => 'belt|timing',
                'job_needles' => ['timing belt', 'timing chain', 'timing kit', 'cambelt', 'cam belt'],
                'companion_key' => 'coolant',
                'companion_label' => 'coolant',
                'companion_needles' => ['coolant', 'antifreeze', 'dex-cool', 'dexcool', 'coolant flush', 'coolant service'],
                'support_count' => 3,
                'exception_count' => 0,
                'source' => 'seed',
            ],
        ];
    }

    public static function install(): void
    {
        $now = Carbon::now();

        foreach (self::defaults() as $row) {
            DB::table('estimate_companion_patterns')->updateOrInsert(
                [
                    'job_key' => $row['job_key'],
                    'companion_key' => $row['companion_key'],
                ],
                [
                    'job_needles' => json_encode($row['job_needles']),
                    'companion_label' => $row['companion_label'],
                    'companion_needles' => json_encode($row['companion_needles']),
                    'support_count' => $row['support_count'],
                    'exception_count' => $row['exception_count'],
                    'source' => $row['source'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
