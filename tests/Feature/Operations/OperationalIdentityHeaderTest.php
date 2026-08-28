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

    $identity = OperationalIdentityPresenter::forRepairOrder($repairOrder);

    expect($identity['vehicle']['subtitle'])->toBe('Black · 3.7L V6')
        ->and(collect($identity['vehicle']['lines'])->pluck('label')->all())
        ->toContain('Mileage')
        ->not->toContain('Mileage in')
        ->not->toContain('Color')
        ->not->toContain('Engine')
        ->and(collect($identity['vehicle']['lines'])->firstWhere('label', 'Mileage')['value'])->toBe('165,604 / —')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Advisor')['value'])->toBe('Lane Advisor')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Technician')['value'])->toBe('Bay Tech')
        ->and($identity['visit']['title'])->toBe('RO #'.$repairOrder->repair_order_id);
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
        ->and($serviceLane['financial']['due'])->toBe('—');
});

test('repair order review header shows service lane identity band without presentation card', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-service-lane-band', false)
        ->assertSee('>Customer</p>', false)
        ->assertSee('>Vehicle</p>', false)
        ->assertSee('>Visit</p>', false)
        ->assertSee('Amber Adams')
        ->assertSee('(719) 229-7105')
        ->assertSee('165,604')
        ->assertSee('Lane Advisor')
        ->assertDontSee('ops-service-lane-footer', false)
        ->assertSee('ops-mileage-inline')
        ->assertSee("task: 'mileage'", false)
        ->assertSee('data-workspace-modal-form="mileage"', false)
        ->assertDontSee('arkRepairOrderMileage')
        ->assertSee('>In</span>', false)
        ->assertSee('>Out</span>', false)
        ->assertDontSee('Advisor:')
        ->assertDontSee('Presentation')
        ->assertDontSee('recommendation pending review');
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

    expect($identity['visit']['posture'])->toBeNull();
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

    expect($identity['visit']['title'])->toBe('Estimate · RO #1547')
        ->and(collect($identity['visit']['lines'])->firstWhere('label', 'Technician')['value'])->toBe('Bay Tech')
        ->and(collect($identity['vehicle']['lines'])->pluck('label')->all())->toContain('Plate', 'VIN', 'Mileage');
});

test('estimate snapshot visit status follows concern authorization not lifecycle label', function () {
    $identity = OperationalIdentityPresenter::fromSnapshot([
        'document_type' => 'estimate',
        'pdf_document_label' => 'Estimate',
        'repair_order' => [
            'repair_order_id' => 1591,
            'status' => 'approved',
            'status_label' => 'Approved',
            'advisor_name' => 'Alex Rivera',
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

    expect(collect($identity['visit']['lines'])->firstWhere('label', 'Status')['value'])
        ->toBe('Deferred for follow-up');
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

    expect($identity['visit']['title'])->toBe('Final Invoice · RO #42')
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
