<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Labor\LaborDiagnosticOverlapObservation;
use App\Ark\Operations\LaborGuides\Rte\RteLaborGuideContext;
use App\Ark\Operations\LaborGuides\Rte\RteLaborLookup;
use App\Ark\Operations\LaborGuides\Rte\RteVehicleResolver;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RteLaborGuideFixtures;

uses(RefreshDatabase::class);

test('rte vehicle resolver matches model name to car description', function (): void {
    RteLaborGuideFixtures::seedSmoke();

    $candidates = app(RteVehicleResolver::class)->candidates(2010, 'GMC', 'Acadia');

    expect($candidates)->toHaveCount(1)
        ->and($candidates->first()->car_id_code)->toBe('DTGB')
        ->and($candidates->first()->car_desc)->toBe('ACADIA');
});

test('rte vehicle resolver prefers ram pickup over legacy c series for 2500', function (): void {
    RteLaborGuideFixtures::seedRam2500Candidates();

    $best = app(RteVehicleResolver::class)->bestCarIdCode(2012, 'Ram', '2500');

    expect($best)->toBe('BTJT');
});

test('rte vehicle resolver matches hyphenated toyota 4runner description', function (): void {
    RteLaborGuideFixtures::seedFourRunnerCandidates();

    $candidates = app(RteVehicleResolver::class)
        ->candidates(1995, 'Toyota', '4Runner');

    expect($candidates)->not->toBeEmpty()
        ->and($candidates->pluck('car_id_code')->all())->toContain('7TDA');
});

test('rte labor search uses readable engine labels for toyota 4runner radiator variants', function (): void {
    RteLaborGuideFixtures::seedFourRunnerCandidates();

    DB::table('rte_engtbl')->insert([
        [
            'mod_id_code' => '7TDB',
            'eng_id_code' => '731IG0',
            'eng_desc' => '2367cc 22RE BOSCH AIR FLOW CTRL',
            'lo_yr' => '1986',
            'hi_yr' => '1996',
        ],
        [
            'mod_id_code' => '7TDB',
            'eng_id_code' => '750IA0',
            'eng_desc' => '2958cc 3VZE MULTIPORT FI',
            'lo_yr' => '1988',
            'hi_yr' => '1997',
        ],
    ]);

    DB::table('rte_job_lku')->insert([
        'job_id_code' => '3461',
        'job_desc' => 'R & R RADIATOR',
        'eng_req' => 'S',
    ]);

    DB::table('rte_lab')->insert([
        [
            'lab_id' => '34617TDB22299',
            'vehicle_segment' => '7TDB',
            'lo_yr' => '0000',
            'hi_yr' => '9999',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.7,
            'avg_hr' => 1.5,
            'lo_hr' => 1.3,
        ],
        [
            'lab_id' => '34617TAx22199',
            'vehicle_segment' => '7TAX',
            'lo_yr' => '0000',
            'hi_yr' => '9999',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => '731Ixx',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.3,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
    ]);

    $lookup = app(RteLaborLookup::class);
    $radiator = $lookup->searchJobs('7TDB', 1995, 'radiator', 10);
    $labels = collect($radiator)
        ->filter(fn (array $row): bool => ($row['job_desc'] ?? '') === 'R & R RADIATOR')
        ->pluck('variant_label')
        ->all();

    expect($labels)->toContain('* 4-RUNNER 4X4 · All years')
        ->and($labels)->toContain('2.4L 22RE · All years')
        ->and(collect($labels)->implode(' '))->not->toContain('engine family')
        ->and(collect($labels)->implode(' '))->not->toContain('pattern');
});

test('rte labor search finds radiator and brakes for toyota 4runner', function (): void {
    RteLaborGuideFixtures::seedFourRunnerLaborRows();

    $lookup = app(RteLaborLookup::class);

    $radiator = $lookup->searchJobs('7TDA', 1995, 'radiator', 10);
    $brakes = $lookup->searchJobs('7TDA', 1995, 'brakes', 10);

    expect(collect($radiator)->pluck('job_desc')->all())->toContain('R & R RADIATOR')
        ->and(collect($brakes)->pluck('job_desc')->all())->toContain('R & R FRONT DISC PADS & REPLACE ROTORS');
});

test('rte labor search excludes wrong vehicle segment rows for ram radiator', function (): void {
    RteLaborGuideFixtures::seedRam2500LaborRows();

    $lookup = app(RteLaborLookup::class);

    $radiator = $lookup->searchJobs('BTTT', 2010, 'radiator', 20);
    $labIds = array_column($radiator, 'lab_id');

    expect($radiator)->not->toBeEmpty()
        ->and($radiator[0]['job_desc'])->toBe('R & R RADIATOR')
        ->and($labIds)->not->toContain('3461BTCF12298')
        ->and($radiator[0]['variant_label'])->toContain('5.7L');
});

test('rte labor search ranks exact vehicle segment above wildcard patterns', function (): void {
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_lab')->insert([
        'lab_id' => '3461BTTT12199',
        'vehicle_segment' => 'BTTT',
        'lo_yr' => '2003',
        'hi_yr' => '2014',
        'com_flag' => 'N',
        'add_req' => '1',
        'model1' => null,
        'model2' => null,
        'model3' => null,
        'model4' => null,
        'model5' => null,
        'model6' => null,
        'model7' => null,
        'model8' => null,
        'model9' => null,
        'eng1' => 'B803A0',
        'eng2' => null,
        'eng3' => null,
        'eng4' => null,
        'eng5' => null,
        'eng6' => null,
        'eng7' => null,
        'eng8' => null,
        'eng9' => null,
        'add_id1' => null,
        'add_hr1' => null,
        'add_id2' => null,
        'add_hr2' => null,
        'add_id3' => null,
        'add_hr3' => null,
        'add_id4' => null,
        'add_hr4' => null,
        'add_id5' => null,
        'add_hr5' => null,
        'add_id6' => null,
        'add_hr6' => null,
        'add_id7' => null,
        'add_hr7' => null,
        'add_id8' => null,
        'add_hr8' => null,
        'add_id9' => null,
        'add_hr9' => null,
        'hi_hr' => 1.4,
        'avg_hr' => 1.2,
        'lo_hr' => 1.0,
    ]);

    $lookup = app(RteLaborLookup::class);
    $radiator = $lookup->searchJobs('BTTT', 2010, 'radiator', 5);

    expect($radiator[0]['lab_id'])->toBe('3461BTTT12199')
        ->and($radiator[0]['match_rank'])->toBe(3);
});

test('rte labor search finds radiator and front brakes for ram 2500 via partial vehicle match', function (): void {
    RteLaborGuideFixtures::seedRam2500LaborRows();

    $lookup = app(RteLaborLookup::class);

    $radiator = $lookup->searchJobs('BTJT', 2012, 'radiator', 5);
    $frontBrakes = $lookup->searchJobs('BTJT', 2012, 'front brake', 5);

    expect($radiator)->not->toBeEmpty()
        ->and($radiator[0]['job_desc'])->toBe('R & R RADIATOR')
        ->and($frontBrakes)->not->toBeEmpty()
        ->and($frontBrakes[0]['job_desc'])->toBe('R & R FRONT DISC PADS');
});

test('rte labor search resolves human readable job descriptions', function (): void {
    RteLaborGuideFixtures::seedSmoke();

    $jobs = app(RteLaborLookup::class)->searchJobs('DTGB', 2010, null, 5);

    expect($jobs)->not->toBeEmpty()
        ->and($jobs[0]['job_desc'])->toBe('ADJUST A-C BELT');
});

test('rte labor search returns jobs for repair order vehicle', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedSmoke();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'GMC', 'Acadia');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'DTGB',
            'q' => 'A-C',
        ]))
        ->assertOk()
        ->assertJsonPath('car_id_code', 'DTGB')
        ->assertJsonPath('recommended_job.job_desc', 'ADJUST A-C BELT')
        ->assertJsonCount(0, 'jobs');
});

test('rte labor apply creates labor line with average hours and included add-ons', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedSmoke();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'GMC', 'Acadia');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'DTGB',
            'lab_id' => '11071Bxx22299',
            'hours_basis' => 'avg',
            'include_add_ons' => true,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, '+1 included'));

    $lines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Labor)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->description)->toBe('ADJUST A-C BELT')
        ->and((float) $lines[0]->labor_entered_hours)->toBe(1.46)
        ->and($lines[1]->description)->toBe('4 WHEEL ALIGNMENT')
        ->and((float) $lines[1]->labor_entered_hours)->toBe(0.35);
});

test('rte labor apply can skip included add-ons', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedSmoke();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'GMC', 'Acadia');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'DTGB',
            'lab_id' => '11071Bxx22299',
            'hours_basis' => 'avg',
            'include_add_ons' => false,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Labor)
        ->count())->toBe(1);
});

test('rte labor search exposes included add-on hours', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedSmoke();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'GMC', 'Acadia');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'DTGB',
            'q' => 'A-C',
        ]))
        ->assertOk()
        ->assertJsonPath('recommended_job.total_avg_hr', 1.57)
        ->assertJsonPath('recommended_job.included_add_ons_total.avg', 0.3)
        ->assertJsonPath('recommended_job.included_add_ons.0.description', '4 WHEEL ALIGNMENT')
        ->assertJsonPath('recommended_job.included_add_ons.0.kind', 'bundled_add_on');
});

test('rte labor search returns a recommended radiator row for the vehicle engine', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_engtbl')->insert([
        [
            'mod_id_code' => 'BTTT',
            'eng_id_code' => 'B863A0',
            'eng_desc' => '6.4L OHV MFI (HEMI)',
            'lo_yr' => '2012',
            'hi_yr' => '2021',
        ],
    ]);

    DB::table('rte_lab')->insert([
        [
            'lab_id' => '1421Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.7,
            'avg_hr' => 0.5,
            'lo_hr' => 0.4,
        ],
        [
            'lab_id' => '1431Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.6,
            'avg_hr' => 0.4,
            'lo_hr' => 0.3,
        ],
        [
            'lab_id' => '3461BTTT12199',
            'vehicle_segment' => 'BTTT',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => 'B803A0',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.2,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
        [
            'lab_id' => '3461BTxx12199',
            'vehicle_segment' => 'BTxx',
            'lo_yr' => '1968',
            'hi_yr' => '2023',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => 'B80xxx',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.2,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
    ]);

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2012, 'Ram', '2500');
    $repairOrder->vehicle()->update([
        'displacement_liters' => 6.4,
        'engine' => '6.4L HEMI V8',
    ]);
    $repairOrder->refresh();

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'q' => 'radiator',
        ]))
        ->assertOk()
        ->assertJsonPath('vehicle_engine_label', '6.4L HEMI')
        ->assertJsonPath('recommended_job.job_desc', 'R & R RADIATOR')
        ->assertJsonPath('recommended_job.is_recommended', true)
        ->assertJsonPath('recommended_job.variant_label', fn (string $label): bool => str_contains($label, '6.4L HEMI'))
        ->assertJsonPath('suggested_labor.line_count', 2)
        ->assertJsonPath('vehicle_age_multiplier', 1.15)
        ->assertJsonPath('suggested_labor.total_avg_hr', 1.84)
        ->assertJsonPath('suggested_labor.lines.0.description', 'R & R RADIATOR')
        ->assertJsonPath('suggested_labor.lines.1.description', 'DRAIN & FILL SYSTEM COOLING SYS')
        ->assertJsonPath('suggested_labor.optional_diagnostic_operations.0.description', 'COMBUSTION TEST COOLING SYSTEM')
        ->assertJsonPath('suggested_labor.labor_explanation.avg.padding_on.advisor_summary.tier_label', 'Shop Avg')
        ->assertJsonPath('suggested_labor.labor_explanation.avg.padding_on.advisor_summary.age_label', 'Age +15%')
        ->assertJsonPath('suggested_labor.labor_explanation.avg.padding_on.advisor_summary.total_hours', 2.12)
        ->assertJsonPath('suggested_labor.labor_explanation.avg.padding_on.advisor_summary.includes', [
            'Coolant drain & refill',
        ])
        ->assertJsonMissingPath('suggested_labor.labor_explanation.avg.padding_on.engineering_detail')
        ->assertJsonPath('suggested_labor.labor_explanation.avg.padding_on.advisor_detail.match_attribution.primary.guide_hours', 1)
        ->assertJsonPath('suggested_labor.labor_explanation.avg.padding_on.advisor_detail.match_attribution.primary.final_hours', 1.35)
        ->assertJsonPath('suggested_labor.labor_explanation.avg.padding_on.advisor_detail.match_attribution.final_total', 2.12)
        ->assertJsonPath('suggested_labor.labor_explanation.avg.padding_on.advisor_detail.match_attribution.adjustments', [
            'Shop Avg +0.17',
            'Age +0.18',
        ])
        ->assertJsonPath('vehicle_match.confidence', 'medium')
        ->assertJsonPath('vehicle_match.engine_selection_required', false);
});

test('rte labor search requires engine selection when vehicle record has no engine', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_engtbl')->insert([
        [
            'mod_id_code' => 'BTTT',
            'eng_id_code' => 'B863A0',
            'eng_desc' => '6.4L OHV MFI (HEMI)',
            'lo_yr' => '2012',
            'hi_yr' => '2021',
        ],
    ]);

    seedRamRadiatorLaborSearchRows();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2012, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'q' => 'radiator',
        ]))
        ->assertOk()
        ->assertJsonPath('vehicle_match.confidence', 'low')
        ->assertJsonPath('vehicle_match.engine_selection_required', true)
        ->assertJsonPath('vehicle_match.missing', fn (array $missing): bool => in_array('engine', $missing, true)
            && in_array('vin', $missing, true))
        ->assertJsonCount(2, 'engine_options')
        ->assertJsonPath('recommended_job', null)
        ->assertJsonPath('suggested_labor', null);
});

test('rte labor search unlocks recommendations after advisor selects engine', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_engtbl')->insert([
        [
            'mod_id_code' => 'BTTT',
            'eng_id_code' => 'B863A0',
            'eng_desc' => '6.4L OHV MFI (HEMI)',
            'lo_yr' => '2012',
            'hi_yr' => '2021',
        ],
    ]);

    seedRamRadiatorLaborSearchRows();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2012, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'eng_id_code' => 'B863A0',
            'q' => 'radiator',
        ]))
        ->assertOk()
        ->assertJsonPath('vehicle_match.confidence', 'medium')
        ->assertJsonPath('vehicle_match.engine_selection_required', false)
        ->assertJsonPath('vehicle_match.engine_source', 'advisor_selected')
        ->assertJsonPath('eng_id_code', 'B863A0')
        ->assertJsonPath('recommended_job.job_desc', 'R & R RADIATOR')
        ->assertJsonPath('suggested_labor.line_count', 2);
});

test('rte labor apply suggested package creates separate estimate lines', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_lab')->insert([
        [
            'lab_id' => '1421Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.7,
            'avg_hr' => 0.5,
            'lo_hr' => 0.4,
        ],
        [
            'lab_id' => '1431Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.6,
            'avg_hr' => 0.4,
            'lo_hr' => 0.3,
        ],
        [
            'lab_id' => '3461BTTT12199',
            'vehicle_segment' => 'BTTT',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => 'B803A0',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.2,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
    ]);

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2012, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'lab_id' => '3461BTTT12199',
            'hours_basis' => 'avg',
            'apply_suggested' => true,
            'search_term' => 'radiator',
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $lines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Labor)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->description)->toBe('R & R RADIATOR')
        ->and($lines[1]->description)->toBe('DRAIN & FILL SYSTEM COOLING SYS');
});

test('rte labor search includes related cooling operations for radiator jobs', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_lab')->insert([
        [
            'lab_id' => '1421Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.7,
            'avg_hr' => 0.5,
            'lo_hr' => 0.4,
        ],
        [
            'lab_id' => '1431Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.6,
            'avg_hr' => 0.4,
            'lo_hr' => 0.3,
        ],
        [
            'lab_id' => '3461BTTT12199',
            'vehicle_segment' => 'BTTT',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => 'B803A0',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => '100',
            'add_hr1' => 0.2,
            'add_id2' => '1500',
            'add_hr2' => 0.4,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.2,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
    ]);

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'q' => 'radiator',
        ]))
        ->assertOk()
        ->assertJsonPath('jobs.0.included_add_ons.0.kind', 'related_operation')
        ->assertJsonPath('jobs.0.included_add_ons.0.description', 'DRAIN & FILL SYSTEM COOLING SYS')
        ->assertJsonCount(1, 'jobs.0.included_add_ons')
        ->assertJsonPath('jobs.0.optional_diagnostic_operations.0.kind', 'related_operation')
        ->assertJsonPath('jobs.0.optional_diagnostic_operations.0.description', 'COMBUSTION TEST COOLING SYSTEM')
        ->assertJsonCount(1, 'jobs.0.optional_diagnostic_operations')
        ->assertJsonPath('jobs.0.included_add_ons_total.avg', 0.67)
        ->assertJsonPath('jobs.0.total_avg_hr', 1.84)
        ->assertJsonPath('vehicle_age_multiplier', 1.15);
});

test('rte labor apply creates separate lines for radiator and related cooling operations', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_lab')->insert([
        [
            'lab_id' => '1421Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.7,
            'avg_hr' => 0.5,
            'lo_hr' => 0.4,
        ],
        [
            'lab_id' => '1431Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.6,
            'avg_hr' => 0.4,
            'lo_hr' => 0.3,
        ],
        [
            'lab_id' => '3461BTTT12199',
            'vehicle_segment' => 'BTTT',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => 'B803A0',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => '1500',
            'add_hr1' => 0.4,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.2,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
    ]);

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'lab_id' => '3461BTTT12199',
            'hours_basis' => 'avg',
            'include_add_ons' => true,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, '+1 included'));

    $lines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Labor)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->description)->toBe('R & R RADIATOR')
        ->and((float) $lines[0]->labor_entered_hours)->toBe(1.35)
        ->and($lines[1]->description)->toBe('DRAIN & FILL SYSTEM COOLING SYS')
        ->and((float) $lines[1]->labor_entered_hours)->toBe(0.77);
});

test('rte labor apply can add optional diagnostic operations when selected', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_lab')->insert([
        [
            'lab_id' => '1421Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.7,
            'avg_hr' => 0.5,
            'lo_hr' => 0.4,
        ],
        [
            'lab_id' => '1431Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.6,
            'avg_hr' => 0.4,
            'lo_hr' => 0.3,
        ],
        [
            'lab_id' => '3461BTTT12199',
            'vehicle_segment' => 'BTTT',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => 'B803A0',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => '1500',
            'add_hr1' => 0.4,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.2,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
    ]);

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'lab_id' => '3461BTTT12199',
            'hours_basis' => 'avg',
            'include_add_ons' => true,
            'optional_diagnostic_lab_ids' => ['1431Bxxx22299'],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $lines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Labor)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(3)
        ->and($lines[2]->description)->toBe('COMBUSTION TEST COOLING SYSTEM')
        ->and((float) $lines[2]->labor_entered_hours)->toBe(0.66);
});

test('rte labor apply can skip older vehicle padding when unchecked', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    DB::table('rte_lab')->insert([
        [
            'lab_id' => '1421Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.7,
            'avg_hr' => 0.5,
            'lo_hr' => 0.4,
        ],
        [
            'lab_id' => '1431Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.6,
            'avg_hr' => 0.4,
            'lo_hr' => 0.3,
        ],
        [
            'lab_id' => '3461BTTT12199',
            'vehicle_segment' => 'BTTT',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => 'B803A0',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.2,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
    ]);

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'lab_id' => '3461BTTT12199',
            'hours_basis' => 'avg',
            'include_add_ons' => true,
            'apply_vehicle_age_padding' => false,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $lines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Labor)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(2)
        ->and((float) $lines[0]->labor_entered_hours)->toBe(1.17)
        ->and((float) $lines[1]->labor_entered_hours)->toBe(0.67);
});

test('rte labor guide context blocks repair orders without vehicle year make model', function (): void {
    RteLaborGuideFixtures::seedSmoke();

    [$repairOrder] = createRteWorksheetRepairOrder(2010, 'GMC', 'Acadia');

    $repairOrder->vehicle()->update([
        'year' => null,
        'make' => null,
        'model' => null,
    ]);

    $repairOrder->refresh();

    $context = app(RteLaborGuideContext::class)->forRepairOrder($repairOrder);

    expect($context['available'])->toBeFalse()
        ->and($context['blocked_reason'])->toContain('year, make, and model');
});

test('estimate edit shows rte guide button when rte data is imported even if vehicle is blocked', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedSmoke();

    [$repairOrder] = createRteWorksheetRepairOrder(2016, 'Subaru', 'Outback');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Labor Guide', false)
        ->assertDontSee('Repair Time Engine', false);
});

test('estimate edit shows rte guide button for matching vehicle', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedSmoke();

    [$repairOrder] = createRteWorksheetRepairOrder(2010, 'GMC', 'Acadia');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Labor Guide', false)
        ->assertDontSee('Repair Time Engine', false);
});

test('rte labor search warns about diagnostic overlap when ro already has diagnostic labor', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();
    seedRamRadiatorLaborSearchRows();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Cooling system overheating diagnosis',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 16500,
        'total_cents' => 16500,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.00',
        'labor_billed_hours' => '1.00',
        'labor_rate_cents' => 16500,
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'q' => 'radiator',
        ]))
        ->assertOk()
        ->assertJsonPath(
            'suggested_labor.labor_explanation.diagnostic_overlap.advisor_summary.overlap_warning',
            LaborDiagnosticOverlapObservation::ADVISOR_WARNING,
        )
        ->assertJsonPath(
            'suggested_labor.labor_explanation.diagnostic_overlap.advisor_detail.diagnostic_overlap.package_line.label',
            'Combustion Test',
        )
        ->assertJsonPath(
            'suggested_labor.labor_explanation.diagnostic_overlap.advisor_detail.diagnostic_overlap.existing_line.description',
            'Cooling system overheating diagnosis',
        )
        ->assertJsonMissingPath('suggested_labor.labor_explanation.diagnostic_overlap.engineering_detail')
        ->assertJsonPath('suggested_labor.total_avg_hr', 1.84);
});

test('rte labor search does not warn about diagnostic overlap without existing diagnostic labor', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();
    seedRamRadiatorLaborSearchRows();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'q' => 'radiator',
        ]))
        ->assertOk()
        ->assertJsonMissingPath('suggested_labor.labor_explanation.diagnostic_overlap');
});

test('rte diagnostic overlap warning does not alter applied labor hours', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();
    seedRamRadiatorLaborSearchRows();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Cooling system overheating diagnosis',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 16500,
        'total_cents' => 16500,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.00',
        'labor_billed_hours' => '1.00',
        'labor_rate_cents' => 16500,
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->getJson(route('operations.repair-orders.rte-labor.search', [
            'repairOrder' => $repairOrder,
            'concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'q' => 'radiator',
        ]))
        ->assertOk()
        ->assertJsonPath(
            'suggested_labor.labor_explanation.diagnostic_overlap.advisor_summary.overlap_warning',
            LaborDiagnosticOverlapObservation::ADVISOR_WARNING,
        );

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'BTTT',
            'lab_id' => '3461BTTT12199',
            'hours_basis' => 'avg',
            'include_add_ons' => true,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $lines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Labor)
        ->orderBy('id')
        ->get();

    expect($lines)->toHaveCount(3)
        ->and($lines[0]->description)->toBe('Cooling system overheating diagnosis')
        ->and((float) $lines[1]->labor_entered_hours)->toBe(1.35)
        ->and($lines[2]->description)->toBe('DRAIN & FILL SYSTEM COOLING SYS')
        ->and((float) $lines[2]->labor_entered_hours)->toBe(0.77);
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern}
 */
function createRteWorksheetRepairOrder(int $year, string $make, string $model): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '5550100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => $year,
        'make' => $make,
        'model' => $model,
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake concern',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    return [$repairOrder, $concern];
}

function seedRamRadiatorLaborSearchRows(): void
{
    DB::table('rte_lab')->insert([
        [
            'lab_id' => '1421Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.7,
            'avg_hr' => 0.5,
            'lo_hr' => 0.4,
        ],
        [
            'lab_id' => '1431Bxxx22299',
            'vehicle_segment' => 'Bxxx',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => null,
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => null,
            'add_hr1' => null,
            'add_id2' => null,
            'add_hr2' => null,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 0.6,
            'avg_hr' => 0.4,
            'lo_hr' => 0.3,
        ],
        [
            'lab_id' => '3461BTTT12199',
            'vehicle_segment' => 'BTTT',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
            'com_flag' => 'N',
            'add_req' => '1',
            'model1' => null,
            'model2' => null,
            'model3' => null,
            'model4' => null,
            'model5' => null,
            'model6' => null,
            'model7' => null,
            'model8' => null,
            'model9' => null,
            'eng1' => 'B803A0',
            'eng2' => null,
            'eng3' => null,
            'eng4' => null,
            'eng5' => null,
            'eng6' => null,
            'eng7' => null,
            'eng8' => null,
            'eng9' => null,
            'add_id1' => '100',
            'add_hr1' => 0.2,
            'add_id2' => '1500',
            'add_hr2' => 0.4,
            'add_id3' => null,
            'add_hr3' => null,
            'add_id4' => null,
            'add_hr4' => null,
            'add_id5' => null,
            'add_hr5' => null,
            'add_id6' => null,
            'add_hr6' => null,
            'add_id7' => null,
            'add_hr7' => null,
            'add_id8' => null,
            'add_hr8' => null,
            'add_id9' => null,
            'add_hr9' => null,
            'hi_hr' => 1.2,
            'avg_hr' => 1.0,
            'lo_hr' => 0.8,
        ],
    ]);
}
