<?php

namespace App\Console\Commands;

use App\Ark\Operations\LaborGuides\Rte\RteLaborLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RteSmokeTestCommand extends Command
{
    protected $signature = 'ark:rte-smoke-test
        {--car=DTGB : RTE car_id_code to test}
        {--year=2010 : Model year filter}';

    protected $description = 'Smoke-test RTE labor guide joins (vehicle → job → hi/avg/lo hours).';

    public function handle(RteLaborLookup $lookup): int
    {
        if (! Schema::hasTable('rte_lab')) {
            $this->components->error('RTE tables are not migrated yet. Run: php artisan migrate');

            return self::FAILURE;
        }

        $result = $lookup->smokeTest(
            carIdCode: (string) $this->option('car'),
            modelYear: (int) $this->option('year'),
        );

        if ($result['passed']) {
            $this->components->info($result['message']);

            return self::SUCCESS;
        }

        $this->components->error($result['message']);

        return self::FAILURE;
    }
}
