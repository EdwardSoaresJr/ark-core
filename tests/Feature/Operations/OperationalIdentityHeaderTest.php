<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\RepairOrders\OperationalIdentityPresenter;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\ServiceLaneIdentityPresenter;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('operational identity presenter includes mileage and advisor when data exists', function () {
    $advisor = User::factory()->create(['name' => 'Lane Advisor'])->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create(['name' => 'Bay Tech'])->assignRole(ArkRole::Technician->value);

    $customer = Customer::query()->create([
        'first_name' => 'Amber',
        'last_name' => 'Adams',
        'phone' => '719-229-7105',
        'email' => 'amber@example.com',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2011,
        'make' => 'Acura',
        'model' => 'ZDX',
        'trim' => 'Base',
        'vin' => '2HNYD2H66BH530414',
        'plate' => 'AYLL41',
        'plate_state' => 'CO',
        'color' => 'Black',
        'engine' => '3.7L V6',
        'private_notes' => 'Legacy odometer: 165604',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'assigned_technician_id' => $technician->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Customer reports drivetrain noise.',
    ]);

    EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_type' => FinancialDocumentType::Estimate,
        'document_number' => 1,
        'snapshot_json' => ['generated_by' => ['name' => $advisor->name]],
        'status' => 'draft',
        'created_by' => $advisor->id,
    ]);

    $identity = OperationalIdentityPresenter::forRepairOrder($repairOrder->fresh());

    expect($identity['vehicle']['subtitle'])->toBe('Black · 3.7L V6')
        ->and(collect($identity['vehicle']['lines'])->pluck('label')->all())
        ->not->toContain('Mileage')
        ->not->toContain('Mileage in')
        ->not->toContain('Color')
        ->not->toContain('Engine')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Mileage')['value'])->toBe('165,604')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Advisor')['value'])->toBe('Lane Advisor')
        ->and(collect($identity['visit']['lines'])->pluck('label')->all())->not->toContain('Technician')
        ->and($identity['visit']['title'])->toBe('RO #'.$repairOrder->repair_order_id);

    $repairOrder->concerns()->create([
        'summary' => 'Split job',
        'disposition' => \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    $concern = $repairOrder->concerns()->first();
    $other = User::factory()->create(['name' => 'Other Tech'])->assignRole(ArkRole::Technician->value);
    $concern->workGroups()->create([
        'title' => 'Split job',
        'position' => 1,
        'owner_type' => \App\Ark\Operations\RepairOrders\RepairActionOwnerType::Technician,
        'owner_user_id' => $other->id,
    ]);

    $split = OperationalIdentityPresenter::forRepairOrder($repairOrder->fresh());

    expect(collect($split['visit']['lines'])->pluck('label')->all())->not->toContain('Technician');
});

test('operational identity presenter surfaces customer preferred contact method', function () {
    [$repairOrder] = identityHeaderRepairOrderFixture();

    $repairOrder->customer->update([
        'contact_preference' => LeadContactPreference::Text,
    ]);

    $identity = OperationalIdentityPresenter::forRepairOrder($repairOrder->fresh(['customer']));
    $reachLine = collect($identity['customer']['lines'])->firstWhere('label', 'Reach via');

    expect($reachLine)->not->toBeNull()
        ->and($reachLine['value'])->toBe('Prefers text');
});

test('service lane identity presenter exposes recognition ownership and financial orientation', function () {
    [$repairOrder] = identityHeaderRepairOrderFixture();
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);

    $serviceLane = ServiceLaneIdentityPresenter::forRepairOrder($repairOrder, $totals);

    expect($serviceLane['customer']['name'])->toBe('Amber Adams')
        ->and($serviceLane['customer']['phone'])->toBe('(719) 229-7105')
        ->and($serviceLane['vehicle']['scanMileage'])->toBe('165,604 mi')
        ->and($serviceLane['ownership']['statusLabel'])->toBe('Building Estimate')
        ->and($serviceLane['ownership']['advisor'])->toBe('Lane Advisor')
        ->and($serviceLane['financial']['estimate'])->toBe('$150.00')
        ->and($serviceLane['financial']['approved'])->toBe('$0.00')
        ->and($serviceLane['financial']['due'])->toBe('-');
});

test('repair order review header shows service lane identity band without presentation card', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $response = $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-service-lane-band', false)
        ->assertSee('ops-identity-column-link', false)
        ->assertSee('>Customer</a>', false)
        ->assertSee('ops-billing-class-pill--slate', false)
        ->assertSee(route('operations.customers.show', $repairOrder->customer), false)
        ->assertSee('>Vehicle</p>', false)
        ->assertSee('>Visit</p>', false)
        ->assertSee('Amber Adams')
        ->assertSee('(719) 229-7105')
        ->assertSee('165,604')
        ->assertSee('Lane Advisor')
        ->assertDontSee('ops-service-lane-footer', false)
        ->assertSee('ops-mileage-inline')
        ->assertSee('data-workspace-modal-form="mileage"', false)
        ->assertSee('>In</span>', false)
        ->assertSee('>Out</span>', false)
        ->assertDontSee('Advisor:')
        ->assertDontSee('Presentation')
        ->assertDontSee('recommendation pending review');

    $html = $response->getContent();
    $vehiclePos = strpos($html, '>Vehicle</p>');
    $visitPos = strpos($html, '>Visit</p>');
    $mileagePos = strpos($html, 'ops-mileage-inline');
    $visitEnd = strpos($html, 'id="review-toolbar"');
    $visitHtml = substr($html, $visitPos, $visitEnd - $visitPos);

    expect($vehiclePos)->toBeInt()
        ->and($visitPos)->toBeGreaterThan($vehiclePos)
        ->and($mileagePos)->toBeGreaterThan($visitPos)
        ->and(substr($html, $vehiclePos, $visitPos - $vehiclePos))->not->toContain('ops-mileage-inline')
        ->and($visitHtml)->not->toContain('>Technician<');
});

test('repair order identity band owns customer and vehicle presentation once', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-identity-present="customer"', false)
        ->assertSee('data-identity-present="vehicle"', false)
        ->assertSee('ops-service-lane-band', false)
        ->assertDontSee('RO Context');
});

test('estimate pdf identity band omits internal presentation reminders', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->sole();
    $snapshot = $document->snapshot_json;

    expect($snapshot['totals']['total_cents'])->toBe(15000);

    $pdfHtml = view('operations.documents.estimates.pdf', [
        'document' => $document,
        'snapshot' => $snapshot,
    ])->render();

    expect($pdfHtml)
        ->toContain('Customer')
        ->toContain('Vehicle')
        ->toContain('Visit')
        ->not->toContain('Presentation')
        ->not->toContain('Recommendations pending')
        ->not->toContain('recommendation to review')
        ->not->toContain('Color:')
        ->not->toContain('Trim:')
        ->not->toContain('Engine:')
        ->toContain('Mileage:')
        ->not->toContain('Mileage in:')
        ->not->toContain('Mileage out:');

    $identity = OperationalIdentityPresenter::fromSnapshot($snapshot, customerFacing: true);

    expect($identity['visit']['posture'])->toBeNull()
        ->and(collect($identity['vehicle']['lines'])->pluck('label')->all())->not->toContain('Mileage')
        ->and(collect($identity['visit']['lines'])->pluck('label')->all())->toContain('Mileage');
});

test('closed repair order estimate snapshot still labels visit column as estimate', function () {
    $identity = OperationalIdentityPresenter::fromSnapshot([
        'document_type' => 'estimate',
        'pdf_document_label' => 'Estimate',
        'repair_order' => [
            'repair_order_id' => 1547,
            'status' => 'closed',
            'status_label' => 'Closed',
            'advisor_name' => 'ARK Admin',
            'assigned_technician_name' => 'Bay Tech',
        ],
        'customer' => ['name' => 'Test Customer'],
        'vehicle' => [
            'display_name' => '2011 Acura ZDX',
            'vin' => '2HNYD2H66BH530414',
            'plate' => 'AYLL41',
            'plate_state' => 'CO',
            'color' => 'Black',
            'engine' => '3.7L V6',
            'mileage_in' => 165604,
            'mileage_out' => 165650,
        ],
        'staff' => [
            'execution' => ['technician_name' => 'Bay Tech'],
        ],
        'concerns' => [],
    ], customerFacing: true);

    expect($identity['visit']['title'])->toBe('RO #1547')
        ->and(collect($identity['visit']['lines'])->pluck('label')->all())->not->toContain('Status')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Technician')['value'])->toBe('Bay Tech')
        ->and(collect($identity['vehicle']['lines'])->pluck('label')->all())->toContain('Plate', 'VIN')
        ->and(collect($identity['vehicle']['lines'])->pluck('label')->all())->not->toContain('Mileage')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Mileage in')['value'])->toBe('165,604')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Mileage out')['value'])->toBe('165,650');
});

test('estimate snapshot visit status follows concern authorization not lifecycle label', function () {
    $identity = OperationalIdentityPresenter::fromSnapshot([
        'document_type' => 'estimate',
        'pdf_document_label' => 'Estimate',
        'repair_order' => [
            'repair_order_id' => 1591,
            'status' => 'approved',
            'status_label' => 'Approved',
            'advisor_name' => 'Edward Soares',
        ],
        'customer' => ['name' => 'Emirhan Cadas'],
        'vehicle' => ['display_name' => '2020 Toyota Corolla LE'],
        'staff' => [
            'approval_events' => [[
                'approved_by' => 'Emirhan Cadas',
                'approved_at' => '2026-06-22T21:26:00.000000Z',
                'approved_amount_cents' => 99118,
            ]],
        ],
        'concerns' => [
            ['disposition' => 'deferred'],
            ['disposition' => 'deferred'],
        ],
    ], customerFacing: false);

    expect(collect($identity['visit']['lines'])->pluck('label')->all())
        ->not->toContain('Status');
});

test('invoice pdf identity band labels visit column as invoice', function () {
    $identity = OperationalIdentityPresenter::fromSnapshot([
        'document_type' => 'invoice',
        'repair_order' => [
            'repair_order_id' => 42,
            'status' => 'in_progress',
            'status_label' => 'In Progress',
            'advisor_name' => 'Lane Advisor',
        ],
        'customer' => ['name' => 'Test Customer'],
        'vehicle' => ['display_name' => '2020 Test Car'],
        'concerns' => [],
    ], customerFacing: true);

    expect($identity['visit']['title'])->toBe('RO #42')
        ->and($identity['visit']['status_badge'])->toBe('In Progress')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Advisor')['value'])->toBe('Lane Advisor');
});

test('invoice pdf identity band falls back to repair order advisor when generated by is missing', function () {
    $identity = OperationalIdentityPresenter::fromSnapshot([
        'document_type' => 'invoice',
        'repair_order' => [
            'repair_order_id' => 42,
            'status' => 'ready_pickup',
            'status_label' => 'Ready Pickup',
            'advisor_name' => 'ARK Admin',
        ],
        'customer' => ['name' => 'Test Customer'],
        'vehicle' => ['display_name' => '2020 Test Car'],
        'concerns' => [],
    ], customerFacing: true);

    expect(collect($identity['visit']['lines'])->firstWhere('label', 'Advisor')['value'])->toBe('ARK Admin');
});

test('customer pdf identity omits unassigned technician placeholders', function () {
    $identity = OperationalIdentityPresenter::fromSnapshot([
        'document_type' => 'estimate',
        'pdf_document_label' => 'Estimate',
        'repair_order' => [
            'repair_order_id' => 1737,
            'status' => 'approved',
            'status_label' => 'Approved',
            'advisor_name' => 'Edward Soares',
            'assigned_technician_name' => 'Needs owner',
        ],
        'customer' => ['name' => 'Brad Bailey'],
        'vehicle' => ['display_name' => '2017 Fiat 124 Spider Classica'],
        'staff' => [
            'execution' => ['technician_name' => 'Needs owner'],
        ],
        'concerns' => [
            ['disposition' => 'approved'],
        ],
    ], customerFacing: true);

    expect($identity['visit']['title'])->toBe('RO #1737')
        ->and($identity['visit']['status_badge'])->toBe('Approved')
        ->and(collect($identity['visit']['lines'])->pluck('label')->all())
        ->not->toContain('Technician')
        ->not->toContain('Status');
});

test('customer pdf identity puts a single mileage on visit when in and out match', function () {
    $identity = OperationalIdentityPresenter::fromSnapshot([
        'document_type' => 'estimate',
        'pdf_document_label' => 'Estimate',
        'repair_order' => [
            'repair_order_id' => 1737,
            'status' => 'approved',
            'status_label' => 'Approved',
            'advisor_name' => 'Edward Soares',
        ],
        'customer' => ['name' => 'Brad Bailey'],
        'vehicle' => [
            'display_name' => '2017 Fiat 124 Spider Classica',
            'vin' => 'JC1NFAEK2H0121920',
            'plate' => 'BIUV55',
            'plate_state' => 'CO',
            'color' => 'Red',
            'engine' => '1.4L L4 vin K SOHC EAM MultiAir',
            'mileage_in' => 44168,
            'mileage_out' => 44168,
        ],
        'concerns' => [
            ['disposition' => 'approved'],
        ],
    ], customerFacing: true);

    expect(collect($identity['vehicle']['lines'])->pluck('label')->all())
        ->toContain('VIN', 'Plate')
        ->not->toContain('Mileage')
        ->and(collect($identity['visit']['lines'])->pluck('label')->all())
        ->toContain('Mileage')
        ->not->toContain('Mileage in')
        ->not->toContain('Mileage out')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Mileage')['value'])->toBe('44,168');
});
