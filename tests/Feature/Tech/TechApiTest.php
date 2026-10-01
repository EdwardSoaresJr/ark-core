<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\InspectionTemplate;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Tech\TechBrakeSpeechParser;
use App\Ark\Tech\TechSchemaSpeechParser;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('advisors cannot sign in to ark tech', function (): void {
    $advisor = User::factory()->create(['password' => 'password'])->assignRole(ArkRole::Advisor->value);

    $this->postJson('/api/tech/auth/login', [
        'email' => $advisor->email,
        'password' => 'password',
        'device_name' => 'Bay tablet',
    ])->assertUnprocessable()
        ->assertJsonFragment(['ARK Tech is for technicians. Advisors use ARK or Shop Glass.']);
});

test('technician can sign in and only see assigned work', function (): void {
    $tech = User::factory()->create(['password' => 'password'])->assignRole(ArkRole::Technician->value);
    $other = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $mine = techAssignedRepairOrder($tech);
    techAssignedRepairOrder($other);

    $login = $this->postJson('/api/tech/auth/login', [
        'email' => $tech->email,
        'password' => 'password',
        'device_name' => 'Bay tablet',
    ])->assertOk();

    expect($login->json('product'))->toBe('ark_tech')
        ->and($login->json('token'))->not->toBeEmpty()
        ->and($login->json('theme.display_mode'))->not->toBeEmpty()
        ->and($login->json('theme.accent_color'))->not->toBeEmpty()
        ->and($login->json('theme.accent_theme'))->not->toBeEmpty();

    $this->withToken($login->json('token'))
        ->getJson('/api/tech/me/work')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.repair_order_id', $mine->repair_order_id)
        ->assertJsonPath('scope', 'assigned')
        ->assertJsonMissingPath('items.0.estimate_total');
});

test('admin tech still only sees assigned work', function (): void {
    $admin = User::factory()->create(['password' => 'password'])->assignRole(ArkRole::Admin->value);
    techAssignedRepairOrder();
    $mine = techAssignedRepairOrder($admin);

    $token = $admin->createToken('ark-tech:test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/tech/me/work')
        ->assertOk()
        ->assertJsonPath('scope', 'assigned')
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.repair_order_id', $mine->repair_order_id);
});

test('technician empty work names the signed-in user', function (): void {
    $tech = User::factory()->create(['name' => 'Kent', 'password' => 'password'])->assignRole(ArkRole::Technician->value);
    techAssignedRepairOrder();

    $token = $tech->createToken('ark-tech:test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/tech/me/work')
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('empty_message', 'Nothing assigned to Kent. Assign this RO to your user in ARK, or sign in as the bay technician.');
});

test('tech dvi walks the same visible ark points not a brake-only subset', function (): void {
    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = techAssignedRepairOrder($tech);
    $token = $tech->createToken('ark-tech:test')->plainTextToken;

    $labels = collect($this->withToken($token)
        ->getJson('/api/tech/repair-orders/'.$repairOrder->repair_order_id.'/inspection')
        ->assertOk()
        ->json('tasks'))
        ->pluck('label');

    expect($labels->first())->toBe('Rear axle brake type')
        ->and($labels)->toContain('LF Tire')
        ->and($labels)->toContain('LF Wheel')
        ->and($labels)->toContain('LF Brake pads')
        ->and($labels)->not->toContain('LR Brake pads')
        ->and($labels)->not->toContain('LR Drum brake');
});

test('technician records structured brake measurements and a photo on the item', function (): void {
    Storage::fake('local');
    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = techAssignedRepairOrder($tech);
    $token = $tech->createToken('ark-tech:test')->plainTextToken;

    $inspection = $this->withToken($token)
        ->getJson('/api/tech/repair-orders/'.$repairOrder->repair_order_id.'/inspection')
        ->assertOk()
        ->json();

    expect($inspection['brake_items'])->not->toBeEmpty()
        ->and($inspection['sections'])->not->toBeEmpty()
        ->and($inspection['brake_items'][0]['interaction']['kind'] ?? null)->not->toBeNull();

    $task = collect($inspection['brake_items'])->first(
        fn (array $row): bool => ($row['interaction']['kind'] ?? '') === 'positioned_measurement',
    );
    $itemId = $task['id'];
    $slotKeys = collect($task['interaction']['slots'])->pluck('key')->values()->all();

    $measurements = [];
    foreach ($slotKeys as $index => $key) {
        $measurements[] = ['name' => $key, 'value' => $index === 0 ? '3' : '2', 'unit' => 'mm'];
    }

    $this->withToken($token)
        ->patch('/api/tech/repair-orders/'.$repairOrder->repair_order_id.'/inspection-items/'.$itemId, [
            'status' => 'needs_attention',
            'source' => 'manual',
            'measurements' => $measurements,
            'photo' => UploadedFile::fake()->image('rear-brakes.jpg', 1200, 900),
        ])
        ->assertOk()
        ->assertJsonPath('saved', true)
        ->assertJsonPath('source', 'manual');
});

test('voice proposal does not write until confirm', function (): void {
    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = techAssignedRepairOrder($tech);
    $token = $tech->createToken('ark-tech:test')->plainTextToken;

    $inspection = $this->withToken($token)
        ->getJson('/api/tech/repair-orders/'.$repairOrder->repair_order_id.'/inspection')
        ->json();
    $task = collect($inspection['brake_items'])->first(
        fn (array $row): bool => ($row['interaction']['kind'] ?? '') === 'positioned_measurement',
    );
    $itemId = $task['id'];
    $transcript = str_contains(strtolower(implode(' ', array_column($task['interaction']['slots'], 'key'))), 'inner')
        ? 'Inner three millimeters, outer two, rotors grooved.'
        : 'Left rear three, right rear two, rotors heavily grooved.';

    $proposal = $this->withToken($token)
        ->postJson('/api/tech/repair-orders/'.$repairOrder->repair_order_id.'/inspection-items/'.$itemId.'/voice', [
            'transcript' => $transcript,
        ])
        ->assertOk()
        ->assertJsonPath('written', false)
        ->assertJsonPath('confirm_required', true)
        ->json('proposal');

    expect($proposal['measurements'])->not->toBeEmpty()
        ->and($proposal['written'] ?? false)->toBeFalse();

    $this->withToken($token)
        ->postJson('/api/tech/repair-orders/'.$repairOrder->repair_order_id.'/inspection-items/'.$itemId.'/voice/confirm', [
            'proposal_id' => $proposal['id'],
        ])
        ->assertOk()
        ->assertJsonPath('source', 'voice_confirmed');
});

test('dragon rewrite failure leaves original text for manual dvi', function (): void {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'down'], 503),
    ]);

    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $token = $tech->createToken('ark-tech:test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/tech/dragon/rewrite', [
            'text' => 'lf outer tie rod loose boot torn',
        ])
        ->assertStatus(503)
        ->assertJsonPath('available', false)
        ->assertJsonPath('original', 'lf outer tie rod loose boot torn');
});

test('schema speech parser maps only configured slots', function (): void {
    $parser = new TechSchemaSpeechParser;
    $four = $parser->parse('Left front eight, right front seven, left rear six, right rear five.', [
        ['key' => 'LF', 'name' => 'LF', 'unit' => 'mm'],
        ['key' => 'RF', 'name' => 'RF', 'unit' => 'mm'],
        ['key' => 'LR', 'name' => 'LR', 'unit' => 'mm'],
        ['key' => 'RR', 'name' => 'RR', 'unit' => 'mm'],
    ]);
    expect($four['measurements'])->toBe([
        ['name' => 'LF', 'value' => '8', 'unit' => 'mm'],
        ['name' => 'RF', 'value' => '7', 'unit' => 'mm'],
        ['name' => 'LR', 'value' => '6', 'unit' => 'mm'],
        ['name' => 'RR', 'value' => '5', 'unit' => 'mm'],
    ]);

    $custom = $parser->parse('CCA is 480', [
        ['key' => 'cca', 'name' => 'CCA', 'unit' => 'A'],
    ]);
    expect($custom['measurements'])->toBe([
        ['name' => 'cca', 'value' => '480', 'unit' => 'A'],
    ]);
});

test('tech dvi payload follows a shop template that is not the Demo Auto Repair brake list', function (): void {
    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = techAssignedRepairOrder($tech);
    $template = InspectionTemplate::query()->create([
        'name' => 'Shop B lining',
        'slug' => 'shop-b-lining-'.uniqid(),
        'enabled' => true,
        'is_default' => false,
        'position' => 40,
    ]);
    $category = $template->categories()->create(['name' => 'Brake System', 'position' => 0]);
    $category->items()->create([
        'label' => 'Brake lining',
        'point_key' => 'lining_four_corner',
        'position' => 0,
        'enabled' => true,
        'requires_photo' => true,
        'measurement_unit' => 'mm',
        'measurement_slots' => [
            ['key' => 'LF', 'name' => 'LF', 'unit' => 'mm', 'required' => true, 'type' => 'number'],
            ['key' => 'RF', 'name' => 'RF', 'unit' => 'mm', 'required' => true, 'type' => 'number'],
            ['key' => 'LR', 'name' => 'LR', 'unit' => 'mm', 'required' => true, 'type' => 'number'],
            ['key' => 'RR', 'name' => 'RR', 'unit' => 'mm', 'required' => true, 'type' => 'number'],
        ],
    ]);
    $repairOrder->forceFill(['required_inspection_template_id' => $template->id])->save();

    $token = $tech->createToken('ark-tech:test')->plainTextToken;
    $json = $this->withToken($token)
        ->getJson('/api/tech/repair-orders/'.$repairOrder->repair_order_id.'/inspection')
        ->assertOk()
        ->json();

    $lining = collect($json['tasks'])->firstWhere('label', 'Brake lining');
    expect($lining['interaction']['kind'])->toBe('positioned_measurement')
        ->and($lining['interaction']['slots'])->toHaveCount(4)
        ->and($lining['interaction']['photo_required'])->toBeTrue()
        ->and($lining['progress']['label'])->toBe('Brake Fluid & Parking Brake · 1 of 1');
});

test('tech dvi renderer schema supports a custom non-brake measurement item', function (): void {
    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = techAssignedRepairOrder($tech);
    $template = InspectionTemplate::query()->create([
        'name' => 'Shop C electrical',
        'slug' => 'shop-c-electrical-'.uniqid(),
        'enabled' => true,
        'is_default' => false,
        'position' => 41,
    ]);
    $category = $template->categories()->create(['name' => 'Underhood', 'position' => 0]);
    $category->items()->create([
        'label' => 'Battery CCA',
        'point_key' => 'battery_cca',
        'position' => 0,
        'enabled' => true,
        'measurement_name' => 'CCA',
        'measurement_unit' => 'A',
        'measurement_slots' => [
            ['key' => 'cca', 'name' => 'CCA', 'unit' => 'A', 'required' => true, 'type' => 'number'],
        ],
    ]);
    $repairOrder->forceFill(['required_inspection_template_id' => $template->id])->save();

    $token = $tech->createToken('ark-tech:test')->plainTextToken;
    $json = $this->withToken($token)
        ->getJson('/api/tech/repair-orders/'.$repairOrder->repair_order_id.'/inspection')
        ->assertOk()
        ->json();

    $cca = collect($json['tasks'])->firstWhere('label', 'Battery CCA');
    expect($cca['interaction']['kind'])->toBe('measurement')
        ->and($cca['interaction']['slots'][0]['key'])->toBe('cca')
        ->and($cca['section_name'])->toBe('Underhood');
});

test('brake speech parser never swaps laterality', function (): void {
    $parsed = (new TechBrakeSpeechParser)->parse('Left rear three millimeters, right rear two, both rotors heavily grooved.');

    expect($parsed['measurements'])->toBe([
        ['name' => 'LR', 'value' => '3', 'unit' => 'mm'],
        ['name' => 'RR', 'value' => '2', 'unit' => 'mm'],
    ])->and($parsed['rotor_condition'])->toBe('grooved');

    $bare = (new TechBrakeSpeechParser)->parse('Left three, right two.');
    expect($bare['measurements'])->toContain(['name' => 'L', 'value' => '3', 'unit' => 'mm'])
        ->and($bare['measurements'])->toContain(['name' => 'R', 'value' => '2', 'unit' => 'mm']);
});

function techAssignedRepairOrder(?User $assignedTechnician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Tech',
        'last_name' => 'Customer',
        'phone' => '7195552100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'TECH1',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '2HGFC2F59JH000001',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Rear brakes grind.',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Rear brakes grind',
        'customer_states' => 'Customer states rear brakes grind.',
        'disposition' => RepairOrderConcernDisposition::Draft->value,
        'position' => 0,
    ]);

    if ($assignedTechnician !== null) {
        $repairOrder->forceFill(['assigned_technician_id' => $assignedTechnician->id])->save();
    }

    return $repairOrder->fresh(['concerns']);
}
