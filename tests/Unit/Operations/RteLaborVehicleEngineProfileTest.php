<?php

use App\Ark\Operations\LaborGuides\Rte\RteLaborVehicleEngineProfile;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;


test('rte vehicle engine profile prefers vehicle displacement when choosing primary engine', function (): void {
    DB::table('rte_engtbl')->insert([
        [
            'mod_id_code' => 'BTTT',
            'eng_id_code' => 'B803A0',
            'eng_desc' => '5.7L MULTIPORT FI (HEMI)',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
        ],
        [
            'mod_id_code' => 'BTTT',
            'eng_id_code' => 'B863A0',
            'eng_desc' => '6.4L OHV MFI (HEMI)',
            'lo_yr' => '2012',
            'hi_yr' => '2021',
        ],
    ]);

    $vehicle = new Vehicle([
        'year' => 2016,
        'make' => 'Ram',
        'model' => '2500',
        'displacement_liters' => 6.4,
        'engine' => '6.4L HEMI V8',
    ]);

    $profile = RteLaborVehicleEngineProfile::forVehicle($vehicle, 'BTTT', 2016);

    expect($profile->primaryEngineLabel())->toBe('6.4L HEMI');
});

test('rte vehicle engine profile scores primary engine higher on shared wildcard rows', function (): void {
    DB::table('rte_engtbl')->insert([
        [
            'mod_id_code' => 'BTTT',
            'eng_id_code' => 'B803A0',
            'eng_desc' => '5.7L MULTIPORT FI (HEMI)',
            'lo_yr' => '2003',
            'hi_yr' => '2014',
        ],
        [
            'mod_id_code' => 'BTTT',
            'eng_id_code' => 'B863A0',
            'eng_desc' => '6.4L OHV MFI (HEMI)',
            'lo_yr' => '2012',
            'hi_yr' => '2021',
        ],
    ]);

    $vehicle = new Vehicle([
        'displacement_liters' => 6.4,
        'engine' => '6.4L HEMI',
    ]);

    $profile = RteLaborVehicleEngineProfile::forVehicle($vehicle, 'BTTT', 2016);

    expect($profile->engineMatchScore([
        'eng1' => 'B80xxx',
    ]))->toBe(120);
});
