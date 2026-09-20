<?php

use App\Ark\Operations\Labor\LaborEngineMatchSource;
use App\Ark\Operations\LaborGuides\Rte\RteLaborVehicleEngineProfile;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Vehicles\VehicleMatchConfidenceLevel;
use App\Ark\Operations\Vehicles\VehicleMatchProjection;
use Illuminate\Support\Collection;

test('vehicle match projection is low when engine is unknown on incomplete vehicle record', function (): void {
    $vehicle = new Vehicle([
        'year' => 2010,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $engineProfile = new RteLaborVehicleEngineProfile(
        primaryEngineLabel: '5.7L HEMI',
        engines: collect([
            (object) ['eng_id_code' => 'B803A0', 'eng_desc' => '5.7L MULTIPORT FI (HEMI)', 'lo_yr' => '2003', 'hi_yr' => '2014'],
            (object) ['eng_id_code' => 'B863A0', 'eng_desc' => '6.4L OHV MFI (HEMI)', 'lo_yr' => '2012', 'hi_yr' => '2021'],
        ]),
        primaryEngine: (object) ['eng_id_code' => 'B803A0', 'eng_desc' => '5.7L MULTIPORT FI (HEMI)', 'lo_yr' => '2003', 'hi_yr' => '2014'],
        matchSource: LaborEngineMatchSource::AssumedDefault,
        matchScore: 0,
    );

    $projection = (new VehicleMatchProjection)->build(
        vehicle: $vehicle,
        engineProfile: $engineProfile,
        applicationLabel: 'R 2500-3500 4X4 PICK-UP (2000-2018)',
        engineSelectionRequired: true,
    );

    expect($projection['confidence'])->toBe(VehicleMatchConfidenceLevel::Low->value)
        ->and($projection['engine_selection_required'])->toBeTrue()
        ->and($projection['matched'])->toBe(['year', 'make', 'model'])
        ->and($projection['missing'])->toContain('engine', 'vin', 'drive_type')
        ->and($projection['application_label'])->toBe('R 2500-3500 4X4 PICK-UP (2000-2018)')
        ->and($projection['explanation'])->toContain('Engine unknown', 'Using broad application group')
        ->and($projection['engine_assumption']['label'])->toBe('5.7L HEMI');
});

test('vehicle match projection is medium when advisor selects engine', function (): void {
    $vehicle = new Vehicle([
        'year' => 2010,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $engineProfile = new RteLaborVehicleEngineProfile(
        primaryEngineLabel: '6.4L HEMI',
        engines: collect([
            (object) ['eng_id_code' => 'B863A0', 'eng_desc' => '6.4L OHV MFI (HEMI)', 'lo_yr' => '2012', 'hi_yr' => '2021'],
        ]),
        primaryEngine: (object) ['eng_id_code' => 'B863A0', 'eng_desc' => '6.4L OHV MFI (HEMI)', 'lo_yr' => '2012', 'hi_yr' => '2021'],
        matchSource: LaborEngineMatchSource::AdvisorSelected,
        matchScore: 120,
    );

    $projection = (new VehicleMatchProjection)->build(
        vehicle: $vehicle,
        engineProfile: $engineProfile,
        applicationLabel: 'R 2500-3500 4X4 PICK-UP (2000-2018)',
        engineSelectionRequired: false,
    );

    expect($projection['confidence'])->toBe(VehicleMatchConfidenceLevel::Medium->value)
        ->and($projection['matched'])->toContain('engine')
        ->and($projection['explanation'])->toContain('Engine known', 'VIN unavailable');
});

test('vehicle match projection is high when vin decoded and engine known', function (): void {
    $vehicle = new Vehicle([
        'year' => 2010,
        'make' => 'Ram',
        'model' => '2500',
        'engine' => '6.4L HEMI V8',
        'displacement_liters' => 6.4,
        'vin' => '1D7RB1GP5AS123456',
        'normalized_vehicle_key' => 'ram-2500-2010',
    ]);

    $engineProfile = new RteLaborVehicleEngineProfile(
        primaryEngineLabel: '6.4L HEMI',
        engines: collect([
            (object) ['eng_id_code' => 'B863A0', 'eng_desc' => '6.4L OHV MFI (HEMI)', 'lo_yr' => '2012', 'hi_yr' => '2021'],
        ]),
        primaryEngine: (object) ['eng_id_code' => 'B863A0', 'eng_desc' => '6.4L OHV MFI (HEMI)', 'lo_yr' => '2012', 'hi_yr' => '2021'],
        matchSource: LaborEngineMatchSource::VehicleRecord,
        matchScore: 140,
    );

    $projection = (new VehicleMatchProjection)->build(
        vehicle: $vehicle,
        engineProfile: $engineProfile,
        applicationLabel: 'R 2500-3500 4X4 PICK-UP (2000-2018)',
        engineSelectionRequired: false,
    );

    expect($projection['confidence'])->toBe(VehicleMatchConfidenceLevel::High->value)
        ->and($projection['matched'])->toContain('engine', 'vin')
        ->and($projection['explanation'])->toContain('VIN decoded', 'Engine known')
        ->and($projection['explanation'])->not->toContain('Using broad application group');
});
