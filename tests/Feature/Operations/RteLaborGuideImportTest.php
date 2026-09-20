<?php

use App\Ark\Operations\LaborGuides\Rte\RteCsvImporter;
use App\Ark\Operations\LaborGuides\Rte\RteLaborLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\RteLaborGuideFixtures;


function seedRteSmokeFixtures(): void
{
    RteLaborGuideFixtures::seedSmoke();
}

test('rte labor lookup joins vehicle to labor hours', function (): void {
    seedRteSmokeFixtures();

    $lookup = app(RteLaborLookup::class);
    $rows = $lookup->laborForVehicle('DTGB', 2010, 5);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['car_desc'])->toBe('ACADIA')
        ->and((float) $rows[0]['book_avg_hr'])->toBe(1.1)
        ->and((float) $rows[0]['avg_hr'])->toBe(1.27)
        ->and($rows[0]['job_desc'])->toBe('ADJUST A-C BELT');
});

test('rte smoke test passes with seeded fixtures', function (): void {
    seedRteSmokeFixtures();

    $result = app(RteLaborLookup::class)->smokeTest('DTGB', 2010);

    expect($result['passed'])->toBeTrue()
        ->and($result['message'])->toContain('ACADIA')
        ->and($result['message'])->toContain('1.27 hr');
});

test('rte csv importer loads a table from csv', function (): void {
    $directory = storage_path('framework/testing/rte-import-'.uniqid());
    File::ensureDirectoryExists($directory);

    File::put($directory.'/rte_carlvl3.csv', implode("\n", [
        'lvl123_code,car_id_code,car_desc,lo_yr,hi_yr',
        '000300,DTGB,ACADIA,2007,2014',
    ]));

    $imported = app(RteCsvImporter::class)->importTable($directory, 'rte_carlvl3', truncate: true);

    expect($imported)->toBe(1)
        ->and(DB::table('rte_carlvl3')->count())->toBe(1)
        ->and(DB::table('rte_carlvl3')->value('car_desc'))->toBe('ACADIA');

    File::deleteDirectory($directory);
});

test('rte smoke test command reports success', function (): void {
    seedRteSmokeFixtures();

    $this->artisan('ark:rte-smoke-test', ['--car' => 'DTGB', '--year' => 2010])
        ->assertSuccessful()
        ->expectsOutputToContain('ACADIA');
});
