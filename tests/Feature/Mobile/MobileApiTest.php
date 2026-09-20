<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('staff can login and receive sanctum token with permissions', function (): void {
    $user = User::factory()->create([
        'password' => 'password',
    ])->assignRole(ArkRole::Technician->value);

    $response = $this->postJson('/api/mobile/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test iPhone',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'user' => ['id', 'name', 'email'],
            'roles',
            'permissions',
            'home_profile',
            'capabilities' => ['mobile', 'repair_orders', 'findings', 'communications'],
        ]);

    expect($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('permissions'))->toContain('production.access')
        ->and($response->json('home_profile'))->toBe('technician')
        ->and($response->json('home_question'))->toBe('What do I inspect or repair next?')
        ->and($response->json('capabilities.findings'))->toBeTrue()
        ->and($response->json('capabilities.communications'))->toBeFalse()
        ->and($response->json('capabilities.attention'))->toBeFalse()
        ->and(collect($response->json('navigation'))->pluck('key')->contains('comms'))->toBeTrue()
        ->and(collect($response->json('navigation'))->pluck('key')->all())->toBe([
            'home',
            'comms',
            'customers',
            'schedule',
            'apps',
        ]);
});

test('technician my work returns only assigned repair orders', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $otherTech = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $assigned = mobileRepairOrder($technician);
    mobileRepairOrder($otherTech);

    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/work')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.repair_order_id', $assigned->repair_order_id)
        ->assertJsonPath('items.0.customer_name', 'Mobile Customer')
        ->assertJsonPath('items.0.status_tone', 'in_progress')
        ->assertJsonPath('items.0.next_action', 'Continue inspection')
        ->assertJsonPath('items.0.entry_section', 'inspection')
        ->assertJsonStructure([
            'items' => [[
                'next_action',
                'age_label',
                'primary_concern_id',
            ]],
        ]);
});

test('technician can load repair order detail with concern and finding projections', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);

    $token = $technician->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('repair_order.status_tone', 'in_progress')
        ->assertJsonPath('repair_order.concerns.0.title', 'Brake noise on stop')
        ->assertJsonPath('repair_order.concerns.0.customer_narrative', 'Customer states brakes squeal when stopping.')
        ->assertJsonPath('repair_order.concerns.0.findings_count', 0)
        ->assertJsonPath('repair_order.concerns.0.photo_count', 0)
        ->assertJsonPath('repair_order.recent_findings', [])
        ->assertJsonPath('workspace.profile', 'technician')
        ->assertJsonPath('workspace.default_section', 'inspection')
        ->assertJsonStructure([
            'workspace' => [
                'profile',
                'question',
                'sections',
                'default_section',
                'command_bar',
                'header',
                'inspection',
                'health',
                'next',
                'recommendations_queue',
                'alerts',
                'timeline',
            ],
        ]);

    $sectionKeys = collect($response->json('workspace.sections'))->pluck('key')->all();
    expect($sectionKeys)->toContain('overview', 'concerns', 'inspection', 'history');
});

test('multi role admin advisor technician uses advisor workspace on estimate repair orders', function (): void {
    $operator = User::factory()->create()->assignRole([
        ArkRole::Admin->value,
        ArkRole::Advisor->value,
        ArkRole::Technician->value,
    ]);

    $repairOrder = mobileRepairOrder(null);
    $repairOrder->forceFill(['status' => RepairOrderStatus::Estimate->value])->save();

    $token = $operator->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('workspace.profile', 'advisor')
        ->assertJsonPath('workspace.default_section', 'overview')
        ->assertJsonPath('workspace.next.action.section', 'concerns')
        ->assertJsonPath('workspace.next.action.key', 'review_estimate');
});

test('advisor workspace surfaces money, estimate section, and send actions', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = mobileRepairOrder(null, RepairOrderConcernDisposition::Recommended);
    $repairOrder->forceFill(['status' => RepairOrderStatus::Estimate->value])->save();
    $concern = $repairOrder->concerns->first();

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Replace front brake pads',
        'quantity' => 2,
        'unit_price_cents' => 15000,
    ]);

    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)
        ->ensureFinancialTotalsAreCurrent($repairOrder->fresh());

    $token = $advisor->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('workspace.header.estimate_total_label', fn ($v) => is_string($v) && str_starts_with($v, '$'))
        // Unit price is stable regardless of shop tax/fee settings (line totals
        // are not — total_cents carries allocated tax + shop fee per line).
        ->assertJsonPath('repair_order.estimate.groups.0.lines.0.unit_price_label', fn ($v) => is_string($v) && str_contains($v, '150.00'))
        ->assertJsonPath('repair_order.estimate.groups.0.lines.0.total_label', fn ($v) => is_string($v) && str_starts_with($v, '$'));

    expect(collect($response->json('workspace.sections'))->pluck('key')->all())
        ->toContain('estimate');

    expect(collect($response->json('workspace.command_bar'))->pluck('key')->all())
        ->toContain('send_estimate', 'send_payment', 'assign_technician');
});

test('advisor concern cards include subtotal when estimate lines exist', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = mobileRepairOrder(null, RepairOrderConcernDisposition::Recommended);
    $concern = $repairOrder->concerns->first();

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Replace front brake pads',
        'quantity' => 2,
        'unit_price_cents' => 15000,
    ]);

    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)
        ->ensureFinancialTotalsAreCurrent($repairOrder->fresh());

    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('repair_order.concerns.0.subtotal_label', fn ($v) => is_string($v) && str_starts_with($v, '$'));
});

test('advisor estimate splits approved vs waiting work', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    // One approved concern (counts toward approved + estimate) and one still
    // recommended (waiting on the customer). Once anything is approved the
    // estimate total drops recommended work, so "waiting" must come from the
    // recommended bucket, not (estimate − approved).
    $repairOrder = mobileRepairOrder(null, RepairOrderConcernDisposition::Approved);
    $approvedConcern = $repairOrder->concerns->first();

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $approvedConcern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Replace front brake pads',
        'quantity' => 2,
        'unit_price_cents' => 15000,
    ]);

    $waitingConcern = \App\Ark\Operations\RepairOrders\RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Coolant leak',
        'customer_states' => 'Customer states coolant smell.',
        'disposition' => RepairOrderConcernDisposition::Recommended->value,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $waitingConcern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Replace radiator',
        'quantity' => 1,
        'unit_price_cents' => 30000,
    ]);

    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)
        ->ensureFinancialTotalsAreCurrent($repairOrder->fresh());

    $token = $advisor->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('repair_order.estimate.has_unapproved_work', true)
        ->assertJsonPath('repair_order.estimate.approved_total_label', fn ($v) => is_string($v) && str_starts_with($v, '$'))
        ->assertJsonPath('repair_order.estimate.waiting_total_label', fn ($v) => is_string($v) && str_starts_with($v, '$'));

    expect($response->json('repair_order.estimate.approved_total_cents'))->toBeGreaterThan(0);
    expect($response->json('repair_order.estimate.waiting_total_cents'))->toBeGreaterThan(0);
});

test('technician workspace hides money payload and estimate section', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $token = $technician->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('workspace.header.estimate_total_label', null)
        ->assertJsonPath('repair_order.estimate', null);

    expect(collect($response->json('workspace.sections'))->pluck('key')->all())
        ->not->toContain('estimate');

    expect(collect($response->json('workspace.command_bar'))->pluck('key')->all())
        ->not->toContain('send_estimate');
});

test('technician workspace includes intelligence for next action and health', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $repairOrder->forceFill(['waiting_here' => true])->save();

    $token = $technician->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonStructure([
            'workspace' => [
                'health' => [
                    'concern_count',
                    'inspection' => ['total', 'complete', 'remaining', 'progress_fraction', 'next_item'],
                    'recommendations_ready_count',
                    'lifecycle_label',
                    'last_activity',
                ],
                'next' => ['headline', 'label', 'reason', 'decision', 'action'],
                'recommendations_queue',
                'alerts',
                'timeline',
                'confidence' => ['label', 'ready_percent', 'ready_fraction', 'missing_intentional'],
            ],
        ])
        ->assertJsonPath('workspace.next.headline', 'NEXT')
        ->assertJsonPath('workspace.next.action.section', 'inspection')
        ->assertJsonPath('workspace.next.action.key', 'inspect_item')
        ->assertJsonPath('workspace.next.decision.rule', fn ($value) => is_string($value) && $value !== '')
        ->assertJsonPath('workspace.confidence.ready_percent', fn ($value) => $value > 0)
        ->assertJsonPath('workspace.health.inspection.total', fn ($value) => $value > 0)
        ->assertJsonPath('workspace.header.inspection_total', fn ($value) => $value > 0)
        ->assertJsonPath('workspace.header.customer_posture_label', 'Customer waiting');

    $alerts = collect($response->json('workspace.alerts'))->pluck('key')->all();
    expect($alerts)->toContain('customer_waiting');
});

test('advisor can document vehicle condition at arrival as observation photos', function (): void {
    Storage::fake('local');

    $advisor = User::factory()->create(['name' => 'Edward Advisor'])->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder($advisor);
    $concern = $repairOrder->concerns->first();
    $token = $advisor->createToken('test')->plainTextToken;

    foreach (['Front', 'Driver side', 'Passenger side', 'Rear', 'Odometer'] as $angle) {
        $this->withToken($token)
            ->post('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/findings', [
                'intent' => InspectionFindingIntent::Observation->value,
                'label' => 'Walk-around — '.$angle,
                'repair_order_concern_id' => $concern->id,
                'photo' => UploadedFile::fake()->image(strtolower($angle).'.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('finding.intent', InspectionFindingIntent::Observation->value);
    }

    expect($repairOrder->fresh()->inspection?->items()->count())->toBe(5);
});

test('technician can load concern and finding detail projections', function (): void {
    Storage::fake('local');

    $technician = User::factory()->create(['name' => 'Landon Tech'])->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $concern = $repairOrder->concerns->first();
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->post('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/findings', [
            'intent' => InspectionFindingIntent::Safety->value,
            'label' => 'Front pad worn',
            'measurement_value' => '2',
            'measurement_unit' => 'mm',
            'notes' => 'Needs attention soon',
            'repair_order_concern_id' => $concern->id,
            'photo' => UploadedFile::fake()->image('pad.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $findingId = $repairOrder->fresh()->inspection?->items->first()?->id;

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id)
        ->assertOk()
        ->assertJsonPath('concern.title', 'Brake noise on stop')
        ->assertJsonPath('concern.findings_count', 1)
        ->assertJsonPath('concern.findings.0.label', 'Front pad worn')
        ->assertJsonPath('concern.quick_actions.add_note', true)
        ->assertJsonPath('concern.findings.0.thumbnail_url', fn ($url) => is_string($url) && str_contains($url, 'inspection-photos'));

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/findings/'.$findingId)
        ->assertOk()
        ->assertJsonPath('finding.label', 'Front pad worn')
        ->assertJsonPath('finding.intent_label', 'Safety')
        ->assertJsonPath('finding.concern.title', 'Brake noise on stop')
        ->assertJsonPath('finding.recorded_by', 'Landon Tech');
});

test('technician can add finding with photo on assigned repair order', function (): void {
    Storage::fake('local');

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $repairOrder = mobileRepairOrder($technician);

    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->post('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/findings', [
            'intent' => InspectionFindingIntent::Safety->value,
            'label' => 'Front pad worn',
            'measurement_value' => '2',
            'measurement_unit' => 'mm',
            'notes' => 'Needs attention soon',
            'photo' => UploadedFile::fake()->image('pad.jpg'),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertCreated()
        ->assertJsonPath('finding.label', 'Front pad worn');

    expect($repairOrder->fresh()->inspection?->items)->toHaveCount(1);

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('repair_order.recent_findings.0.label', 'Front pad worn')
        ->assertJsonPath('repair_order.recent_findings.0.has_photo', true)
        ->assertJsonPath('repair_order.recent_findings.0.measurement_summary', '2 mm');
});

test('technician cannot open unassigned repair order', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder();

    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertForbidden();
});

test('advisor can load mobile me and notifications polling surface', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('home_profile', 'advisor')
        ->assertJsonPath('home_question', 'Who needs a response or decision?')
        ->assertJsonPath('capabilities.communications', true)
        ->assertJsonPath('capabilities.customer_reply', true)
        ->assertJsonPath('capabilities.intake', true)
        ->assertJsonPath('capabilities.attention', true)
        ->assertJsonPath('theme.accent_color', '#0099cc')
        ->assertJsonPath('theme.display_mode', 'light')
        ->assertJsonPath('telephony.dial_method', 'native')
        ->assertJsonPath('navigation.0.key', 'home')
        ->assertJsonPath('navigation.1.key', 'comms')
        ->assertJsonPath('navigation.1.label', 'Conversations')
        ->assertJsonPath('navigation.2.key', 'customers')
        ->assertJsonPath('navigation.3.key', 'schedule')
        ->assertJsonPath('navigation.4.key', 'apps')
        ->assertJsonStructure([
            'learning' => ['arkademy_enabled', 'arkademy_url'],
            'user',
            'roles',
            'permissions',
            'navigation' => [['key', 'label', 'enabled']],
            'capabilities' => ['repair_orders', 'findings', 'communications'],
            'telephony' => ['dial_method'],
        ]);

    $this->withToken($token)
        ->getJson('/api/mobile/comms/hub')
        ->assertOk()
        ->assertJsonPath('question', 'Who needs a response or a call?')
        ->assertJsonStructure([
            'telephony' => ['dial_method', 'voice'],
            'active_inbound_call',
            'sections',
            'attention_count',
            'conversations' => ['items', 'count'],
            'poll_after_seconds',
        ]);

    $this->withToken($token)
        ->getJson('/api/mobile/notifications')
        ->assertOk()
        ->assertJsonStructure(['items', 'count', 'poll_after_seconds']);
});

test('advisor can decode vin via mobile tools endpoint', function (): void {
    config()->set('services.partstech.username', null);

    Http::fake([
        'vpic.nhtsa.dot.gov/*' => Http::response([
            'Results' => [[
                'ModelYear' => '2019',
                'Make' => 'Toyota',
                'Model' => 'RAV4',
                'Trim' => 'XLE',
                'EngineModel' => '2.5L',
                'DriveType' => 'All-Wheel Drive',
                'TransmissionStyle' => 'Automatic',
                'FuelTypePrimary' => 'Gasoline',
            ]],
        ]),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mobile/tools/vin-decode', [
            'vin' => '2T3RFREV6KW020202',
        ])
        ->assertOk()
        ->assertJsonPath('vehicle.vin', '2T3RFREV6KW020202')
        ->assertJsonPath('vehicle.make', 'Toyota')
        ->assertJsonPath('vehicle.model', 'Rav4')
        ->assertJsonPath('vehicle.label', '2019 Toyota Rav4 XLE')
        ->assertJsonPath('vehicle.usable', true)
        ->assertJsonPath('vehicle.source_label', 'NHTSA');
});

test('technician cannot decode vin on mobile', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mobile/tools/vin-decode', [
            'vin' => '2T3RFREV6KW020202',
        ])
        ->assertForbidden();
});

test('advisor can run mobile intake check-in flow', function (): void {
    config()->set('services.partstech.username', null);

    Http::fake([
        'vpic.nhtsa.dot.gov/*' => Http::response([
            'Results' => [[
                'ModelYear' => '2019',
                'Make' => 'Toyota',
                'Model' => 'RAV4',
                'Trim' => 'XLE',
                'EngineModel' => '2.5L',
                'DriveType' => 'All-Wheel Drive',
                'TransmissionStyle' => 'Automatic',
            ]],
        ]),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $existingCustomer = Customer::query()->create([
        'first_name' => 'Returning',
        'last_name' => 'Driver',
        'phone' => '7195558800',
    ]);

    $existingVehicle = Vehicle::query()->create([
        'customer_id' => $existingCustomer->id,
        'vin' => '2T3RFREV6KW020202',
        'normalized_vin' => '2T3RFREV6KW020202',
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'RAV4',
    ]);

    $this->withToken($token)
        ->getJson('/api/mobile/intake/vehicles/lookup?q=2T3RFREV6KW020202')
        ->assertOk()
        ->assertJsonPath('match.customer.id', $existingCustomer->id)
        ->assertJsonPath('match.vehicle.id', $existingVehicle->id);

    $this->withToken($token)
        ->getJson('/api/mobile/intake/customers/search?q=Returning')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.name', 'Returning Driver');

    $this->withToken($token)
        ->postJson('/api/mobile/intake/customers', [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'phone' => '7195558801',
        ])
        ->assertCreated()
        ->assertJsonPath('customer.name', 'Walk In');

    $newCustomerId = Customer::query()->where('phone', '7195558801')->value('id');

    $this->withToken($token)
        ->postJson('/api/mobile/intake/customers/'.$newCustomerId.'/vehicles', [
            'vin' => '1C4HJXDG6EW123456',
            'year' => 2014,
            'make' => 'Jeep',
            'model' => 'Wrangler',
        ])
        ->assertCreated()
        ->assertJsonPath('vehicle.label', '2014 Jeep Wrangler');

    $newVehicleId = Vehicle::query()->where('vin', '1C4HJXDG6EW123456')->value('id');

    $this->withToken($token)
        ->postJson('/api/mobile/intake', [
            'customer_id' => $newCustomerId,
            'vehicle_id' => $newVehicleId,
            'visit_mode' => 'waiting_here',
            'concerns' => [
                ['customer_states' => 'Brake noise when stopping.'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('repair_order.customer_name', 'Walk In')
        ->assertJsonPath('repair_order.vehicle_label', '2014 Jeep Wrangler')
        ->assertJsonPath('repair_order.concern_count', 0)
        ->assertJsonPath('repair_order.visit_reason', 'Brake noise when stopping.');

    expect(RepairOrder::query()->where('customer_id', $newCustomerId)->count())->toBe(1);
});

test('advisor can list assignable technicians and assign on mobile intake', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create(['name' => 'Floor Tech'])->assignRole(ArkRole::Technician->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Assign',
        'last_name' => 'Test',
        'phone' => '7195558810',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '2HGFC2F59JH123456',
    ]);

    $this->withToken($token)
        ->getJson('/api/mobile/intake/technicians')
        ->assertOk()
        ->assertJsonPath('requires_assignment', true)
        ->assertJsonFragment(['id' => $technician->id, 'name' => 'Floor Tech']);

    $this->withToken($token)
        ->postJson('/api/mobile/intake', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'visit_mode' => 'drop_off',
            'assigned_technician_id' => $technician->id,
            'mileage_in' => 84210,
            'concerns' => [
                [
                    'customer_states' => 'Check engine light on.',
                    'dtcs_summary' => 'P0303 current, P0300 pending',
                    'verified_findings' => 'Stored misfire codes reported via vGate iCar Pro.',
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('repair_order.assigned_technician_id', $technician->id)
        ->assertJsonPath('repair_order.assigned_technician', 'Floor Tech')
        ->assertJsonPath('repair_order.primary_concern_id', null)
        ->assertJsonPath('repair_order.visit_reason', 'Check engine light on.')
        ->assertJsonPath('repair_order.concern_count', 0);

    $repairOrder = RepairOrder::query()->where('customer_id', $customer->id)->first();
    expect($repairOrder->assigned_technician_id)->toBe($technician->id);
    expect($repairOrder->mileage_in)->toBe(84210);
    expect($repairOrder->concerns)->toHaveCount(0);
    expect($repairOrder->visit_reason)->toBe('Check engine light on.');
});

test('advisor can assign technician on existing repair order via mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create(['name' => 'Late Assign'])->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder();
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/technician-assignment', [
            'assigned_technician_id' => $technician->id,
        ])
        ->assertOk()
        ->assertJsonPath('repair_order.assigned_technician_id', $technician->id)
        ->assertJsonPath('repair_order.assigned_technician', 'Late Assign');

    expect($repairOrder->fresh()->assigned_technician_id)->toBe($technician->id);
});

test('technician cannot access mobile intake endpoints', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/intake/customers/search?q=test')
        ->assertForbidden();

    $this->withToken($token)
        ->postJson('/api/mobile/intake', [
            'customer_id' => 1,
            'vehicle_id' => 1,
            'visit_mode' => 'drop_off',
        ])
        ->assertForbidden();
});

test('technician can update production status on assigned approved concern', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician, disposition: RepairOrderConcernDisposition::Approved);
    $concern = $repairOrder->concerns->first();
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id)
        ->assertOk()
        ->assertJsonPath('concern.production.tracks', true)
        ->assertJsonPath('concern.production.can_update', true)
        ->assertJsonPath('concern.production.status', 'pending');

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/production-status', [
            'production_status' => ScopeProductionStatus::InProgress->value,
        ])
        ->assertOk()
        ->assertJsonPath('concern.production_status', 'in_progress')
        ->assertJsonPath('concern.production_status_label', 'In progress');

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::InProgress);
});

test('technician can update concern inspection fields and add production note', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $concern = $repairOrder->concerns->first();
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id, [
            'verified_findings' => 'Front pads at 2 mm. Rotors have lip.',
            'recommendation' => 'Recommend front brake service when customer is ready.',
        ])
        ->assertOk()
        ->assertJsonPath('concern.verified_findings', 'Front pads at 2 mm. Rotors have lip.')
        ->assertJsonPath('concern.recommendation', 'Recommend front brake service when customer is ready.');

    $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/notes', [
            'description' => 'Customer deferred pads for now.',
        ])
        ->assertCreated()
        ->assertJsonPath('concern.recommendations.0.description', 'Customer deferred pads for now.')
        ->assertJsonPath('concern.recommendations.0.type', 'note');

    expect($concern->fresh()->verified_findings)->toBe('Front pads at 2 mm. Rotors have lip.');
    expect($concern->fresh()->lines)->toHaveCount(1);
});

test('technician can complete approved scope production on mobile', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician, disposition: RepairOrderConcernDisposition::Approved);
    $concern = $repairOrder->concerns->first();
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/production-status', [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertOk()
        ->assertJsonPath('concern.production.status', 'completed')
        ->assertJsonPath('concern.quick_actions.complete_scope', false);

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::Completed);
});

test('technician cannot update production status on unassigned repair order', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder(disposition: RepairOrderConcernDisposition::Approved);
    $concern = $repairOrder->concerns->first();
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/production-status', [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertForbidden();
});

test('production status cannot be set on deferred mobile concern', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician, disposition: RepairOrderConcernDisposition::Deferred);
    $concern = $repairOrder->concerns->first();
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/production-status', [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['production_status']);
});

test('technician can set production in progress on recommended mobile concern', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician, disposition: RepairOrderConcernDisposition::Recommended);
    $concern = $repairOrder->concerns->first();
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/production-status', [
            'production_status' => ScopeProductionStatus::InProgress->value,
        ])
        ->assertOk()
        ->assertJsonPath('concern.production_status', 'in_progress');
});

test('advisor can approve a concern disposition in place from mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder(null, RepairOrderConcernDisposition::Recommended);
    $repairOrder->forceFill(['status' => RepairOrderStatus::Estimate->value])->save();
    $concern = $repairOrder->concerns->first();

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Replace front brake pads',
        'quantity' => 2,
        'unit_price_cents' => 15000,
    ]);

    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id)
        ->assertOk()
        ->assertJsonPath('concern.disposition_control.current', 'recommended')
        ->assertJsonPath('concern.disposition_control.can_update', true)
        ->assertJsonPath('concern.disposition_control.options.0.value', fn ($v) => is_string($v));

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/disposition', [
            'disposition' => RepairOrderConcernDisposition::Approved->value,
        ])
        ->assertOk()
        ->assertJsonPath('concern.disposition_control.current', 'approved');

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved);
});

test('technician cannot set concern disposition on mobile', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician, RepairOrderConcernDisposition::Recommended);
    $concern = $repairOrder->concerns->first();
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id)
        ->assertOk()
        ->assertJsonPath('concern.disposition_control.can_update', false);

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/disposition', [
            'disposition' => RepairOrderConcernDisposition::Approved->value,
        ])
        ->assertForbidden();

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended);
});

test('advisor workspace exposes lifecycle control', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Estimate->value])->save();
    $concern = $repairOrder->concerns->first();
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Diagnose brake noise',
        'quantity' => 1,
        'unit_price_cents' => 12000,
    ]);

    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('workspace.lifecycle.can_update', true)
        ->assertJsonPath('workspace.lifecycle.current.value', RepairOrderStatus::Estimate->value)
        ->assertJsonPath('workspace.lifecycle.options.0.value', fn ($v) => is_string($v));
});

test('technician workspace omits the lifecycle control', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);

    $response = $this->withToken($technician->createToken('test')->plainTextToken)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('workspace.profile', 'technician')
        ->assertJsonPath('workspace.lifecycle', null)
        ->assertJsonPath('workspace.technician_assignment', null);

    expect(collect($response->json('workspace.command_bar'))->pluck('key')->all())
        ->not->toContain('assign_technician');
});

test('advisor workspace exposes technician assignment control', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create(['name' => 'Bay Tech'])->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder();

    $response = $this->withToken($advisor->createToken('test')->plainTextToken)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('workspace.technician_assignment.can_update', true)
        ->assertJsonPath('workspace.technician_assignment.requires_assignment', true)
        ->assertJsonPath('workspace.technician_assignment.technicians.0.id', $technician->id)
        ->assertJsonPath('workspace.technician_assignment.technicians.0.name', 'Bay Tech');

    expect(collect($response->json('workspace.command_bar'))->pluck('key')->all())
        ->toContain('assign_technician');
});

test('advisor workspace exposes add concern command', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();

    $response = $this->withToken($advisor->createToken('test')->plainTextToken)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk();

    expect(collect($response->json('workspace.command_bar'))->pluck('key')->all())
        ->toContain('add_concern');
});

test('advisor can add and delete concerns on mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns', [
            'customer_states' => 'Steering wheel shakes at highway speed.',
        ])
        ->assertCreated()
        ->assertJsonPath('concern.customer_narrative', 'Steering wheel shakes at highway speed.')
        ->assertJsonPath('concern.scope_management.can_delete', true);

    expect($repairOrder->fresh()->concerns)->toHaveCount(2);

    $newConcern = $repairOrder->fresh()->concerns->sortByDesc('id')->first();

    $this->withToken($token)
        ->deleteJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$newConcern->id)
        ->assertOk()
        ->assertJsonPath('message', 'Concern deleted.');

    expect($repairOrder->fresh()->concerns)->toHaveCount(1);
});

test('mobile concern delete is blocked when estimate lines exist', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $concern = $repairOrder->concerns->first();
    $concern->lines()->create([
        'repair_order_id' => $repairOrder->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => 1,
        'unit_price_cents' => 12000,
    ]);

    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id)
        ->assertOk()
        ->assertJsonPath('concern.scope_management.can_delete', false);

    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->deleteJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id)
        ->assertStatus(422);
});

test('technician cannot add concerns on mobile', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);

    $this->withToken($technician->createToken('test')->plainTextToken)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns', [
            'customer_states' => 'Should not stick.',
        ])
        ->assertForbidden();
});

test('advisor workspace exposes add estimate line command and editing projection', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();

    $response = $this->withToken($advisor->createToken('test')->plainTextToken)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk();

    expect(collect($response->json('workspace.command_bar'))->pluck('key')->all())
        ->toContain('add_estimate_line');

    expect($response->json('repair_order.estimate.estimate_editing.can_edit'))->toBeTrue()
        ->and($response->json('repair_order.estimate.estimate_editing.concerns.0.title'))->toBe('Brake noise on stop')
        ->and($response->json('repair_order.estimate.estimate_editing.labor_rate_label'))->toBeString();
});

test('advisor can add and delete estimate lines on mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Draft->value])->save();
    $concern = $repairOrder->concerns->first();
    $token = $advisor->createToken('test')->plainTextToken;

    ShopSettings::current()->update(['default_labor_rate_cents' => 15000]);

    $labor = $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines', [
            'repair_order_concern_id' => $concern->id,
            'type' => 'labor',
            'description' => 'Brake pad replacement',
            'quantity' => 1.5,
        ])
        ->assertCreated()
        ->assertJsonPath('line.type', 'labor')
        ->assertJsonPath('line.description', 'Brake pad replacement')
        ->assertJsonPath('line.can_delete', true)
        ->json('line');

    expect($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::Estimate->value);

    $part = $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines', [
            'repair_order_concern_id' => $concern->id,
            'type' => 'part',
            'description' => 'Front brake pads',
            'quantity' => 1,
            'part_cost' => '45.00',
        ])
        ->assertCreated()
        ->assertJsonPath('line.type', 'part')
        ->json('line');

    expect($repairOrder->fresh()->lines)->toHaveCount(2);

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('repair_order.estimate.has_lines', true)
        ->assertJsonPath('repair_order.estimate.groups.0.lines.0.can_delete', true);

    $this->withToken($token)
        ->deleteJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines/'.$part['id'])
        ->assertOk()
        ->assertJsonPath('message', 'Line deleted.');

    expect($repairOrder->fresh()->lines)->toHaveCount(1);

    $this->withToken($token)
        ->deleteJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines/'.$labor['id'])
        ->assertOk();

    expect($repairOrder->fresh()->lines)->toHaveCount(0);
});

test('technician cannot add estimate lines on mobile', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $concern = $repairOrder->concerns->first();

    $this->withToken($technician->createToken('test')->plainTextToken)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines', [
            'repair_order_concern_id' => $concern->id,
            'type' => 'labor',
            'description' => 'Should not stick',
            'quantity' => 1,
        ])
        ->assertForbidden();
});

test('mobile estimate line store rejects unsupported line types', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $concern = $repairOrder->concerns->first();

    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines', [
            'repair_order_concern_id' => $concern->id,
            'type' => 'fee',
            'description' => 'Shop supplies',
            'quantity' => 1,
            'unit_price' => '5.00',
        ])
        ->assertStatus(422);
});

test('advisor can update estimate lines on mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Draft->value])->save();
    $concern = $repairOrder->concerns->first();
    $token = $advisor->createToken('test')->plainTextToken;

    ShopSettings::current()->update(['default_labor_rate_cents' => 15000]);

    $line = $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines', [
            'repair_order_concern_id' => $concern->id,
            'type' => 'labor',
            'description' => 'Brake pad replacement',
            'quantity' => 1,
        ])
        ->assertCreated()
        ->json('line');

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines/'.$line['id'], [
            'repair_order_concern_id' => $concern->id,
            'type' => 'labor',
            'description' => 'Front brake pad replacement',
            'quantity' => 2.5,
        ])
        ->assertOk()
        ->assertJsonPath('line.description', 'Front brake pad replacement')
        ->assertJsonPath('line.quantity', 2.5)
        ->assertJsonPath('line.can_edit', true)
        ->assertJsonPath('message', 'Line updated.');

    expect($repairOrder->fresh()->lines->first()->description)->toBe('Front brake pad replacement');
});

test('mobile estimate line update rejects type changes', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $concern = $repairOrder->concerns->first();
    $token = $advisor->createToken('test')->plainTextToken;

    $line = $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines', [
            'repair_order_concern_id' => $concern->id,
            'type' => 'labor',
            'description' => 'Diagnostic',
            'quantity' => 1,
        ])
        ->assertCreated()
        ->json('line');

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines/'.$line['id'], [
            'repair_order_concern_id' => $concern->id,
            'type' => 'part',
            'description' => 'Diagnostic',
            'quantity' => 1,
            'part_cost' => '25.00',
        ])
        ->assertStatus(422);
});

test('advisor can move repair order lifecycle from mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Estimate->value])->save();
    $concern = $repairOrder->concerns->first();
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Replace brake pads',
        'quantity' => 2,
        'unit_price_cents' => 15000,
    ]);

    $token = $advisor->createToken('test')->plainTextToken;

    $lifecycle = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->json('workspace.lifecycle');

    $target = collect($lifecycle['options'])
        ->first(fn (array $option): bool => $option['disabled'] === false && $option['kind'] === 'status');

    expect($target)->not->toBeNull();

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/status', [
            'status' => $target['value'],
        ])
        ->assertOk()
        ->assertJsonPath('workspace.lifecycle.current.value', $target['value']);

    expect($repairOrder->fresh()->status->value)->toBe($target['value']);
});

test('technician cannot change repair order lifecycle on mobile', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);

    $this->withToken($technician->createToken('test')->plainTextToken)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/status', [
            'status' => RepairOrderStatus::Completed->value,
        ])
        ->assertForbidden();
});

test('blocked lifecycle move returns a reason from mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Estimate->value])->save();

    // No estimate lines and/or not an allowed forward move — server must refuse
    // with an explanation rather than silently changing state.
    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/status', [
            'status' => RepairOrderStatus::InProgress->value,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($message) => is_string($message) && $message !== '');

    expect($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::Estimate->value);
});

test('advisor can load conversation thread and reply on mobile', function (): void {
        
    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Text',
        'last_name' => 'Customer',
        'phone' => '7195557700',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195557700',
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Are you open Saturday?',
        'occurred_at' => now()->subMinutes(5),
    ]);

    $shopParticipant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Advisor,
        'user_id' => $advisor->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $shopParticipant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'body' => 'Yes — drop off before noon.',
        'occurred_at' => now()->subMinutes(3),
    ]);

    expect(ConversationMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(2);

    // Simulate list/workspace presenters that eager-load only the latest message.
    $conversation->load(['messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1)]);

    $this->withToken($token)
        ->getJson('/api/mobile/communications/'.$conversation->id)
        ->assertOk()
        ->assertJsonPath('thread.headline', 'Text Customer')
        ->assertJsonPath('thread.can_reply', true)
        ->assertJsonPath('thread.poll_after_seconds', 20)
        ->assertJsonCount(2, 'thread.events')
        ->assertJsonPath('thread.events.0.body', 'Are you open Saturday?')
        ->assertJsonPath('thread.events.1.body', 'Yes — drop off before noon.');

    $this->withToken($token)
        ->getJson('/api/mobile/communications')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('poll_after_seconds', 45)
        ->assertJsonPath('items.0.headline', 'Text Customer')
        ->assertJsonCount(2, 'items.0.recent_events');

    bindFakeOutboundSms();
    seedMobileSmsCapability('7195557700');

    $this->withToken($token)
        ->postJson('/api/mobile/communications/'.$conversation->id.'/messages', [
            'body' => 'Yes — drop off before noon.',
        ])
        ->assertCreated()
        ->assertJsonStructure(['message_id']);

    expect(ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', OperationalCommunicationDirection::Outbound)
        ->where('body', 'Yes — drop off before noon.')
        ->exists())->toBeTrue();
});

test('mobile conversation thread merges calls and multi-channel messages', function (): void {
        
    $advisor = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Maricruz',
        'last_name' => 'Garcia',
        'phone' => '7195557701',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195557701',
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Text me when ready.',
        'occurred_at' => now()->subMinutes(10),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Email,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Also emailed the estimate PDF.',
        'occurred_at' => now()->subMinutes(8),
    ]);

    $callSession = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmobilemerge001',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195557701',
        'to_number' => '+17195559999',
        'normalized_from' => '7195557701',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/REmobile001',
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/mobile/conversations/'.$conversation->id)
        ->assertOk();

    $kinds = collect($response->json('thread.events'))->pluck('kind')->all();

    expect($kinds)->toContain('sms', 'email', 'call')
        ->and($response->json('thread.events'))->toHaveCount(3);

    $this->withToken($token)
        ->getJson('/api/mobile/communications/'.$conversation->id)
        ->assertOk();

    $callEvent = collect($response->json('thread.events'))
        ->firstWhere('kind', 'call');

    expect($callEvent['metadata']['has_recording'] ?? false)->toBeTrue()
        ->and($callEvent['metadata']['recording_path'] ?? null)->toBe('/calls/'.$callSession->id.'/recording?kind=recording');
});

test('mobile conversation thread exposes activity projection with context and actions', function (): void {
        
    $advisor = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Mike',
        'last_name' => 'Kindig',
        'phone' => '7195558800',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'GMC',
        'model' => 'Sierra',
    ]);

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval->value,
        'waiting_here' => true,
        'concern_summary' => 'Brakes noise',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558800',
        'status' => ConversationStatus::Open,
    ]);

    ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    $callSession = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAactivity001',
        'customer_id' => $customer->id,
        'repair_order_id' => $repairOrder->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558800',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558800',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(2),
        'owned_by_user_id' => $advisor->id,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/REactivity001',
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/mobile/conversations/'.$conversation->id)
        ->assertOk();

    $response->assertJsonPath('thread.context.customer.name', 'Mike Kindig')
        ->assertJsonPath('thread.context.vehicle.label', '2017 GMC Sierra')
        ->assertJsonPath('thread.context.repair_order.number_label', '#'.$repairOrder->repair_order_id)
        ->assertJsonPath('thread.context.repair_order.vehicle_location_label', 'Vehicle in shop')
        ->assertJsonStructure([
            'thread' => [
                'context',
                'activities',
                'events',
                'composer_actions',
                'live_state',
            ],
        ]);

    $callActivity = collect($response->json('thread.activities'))
        ->firstWhere('kind', 'call');

    expect($callActivity)->not->toBeNull()
        ->and($callActivity['activity_label'])->toBe('Phone call')
        ->and($callActivity['day_label'])->toBe('Today')
        ->and(collect($callActivity['actions'])->pluck('key')->all())
        ->toContain('play_recording', 'call_back');

    $composerKeys = collect($response->json('thread.composer_actions'))->pluck('key')->all();
    expect($composerKeys)->toContain('estimate', 'payment', 'inspection');

    $callAction = collect($response->json('thread.composer_actions'))
        ->firstWhere('key', 'call');

    expect($callAction['params']['dial_method'] ?? null)->toBe('native');
});

test('mobile me and conversation call actions use native dial without Twilio voice', function (): void {
    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    \App\Ark\Operations\Telephony\TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => \App\Ark\Operations\Telephony\TelephonyEndpointType::Cell,
        'destination' => '+17195551234',
        'user_id' => $advisor->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('telephony.dial_method', 'native');

    $customer = Customer::query()->create([
        'first_name' => 'Callback',
        'last_name' => 'Customer',
        'phone' => '7195558801',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558801',
        'status' => ConversationStatus::Open,
    ]);

    ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/mobile/conversations/'.$conversation->id)
        ->assertOk();

    $callAction = collect($response->json('thread.composer_actions'))
        ->firstWhere('key', 'call');

    expect($callAction['label'])->toBe('Call')
        ->and($callAction['params']['dial_method'])->toBe('native')
        ->and($callAction['params']['phone'])->not->toBeEmpty();
});

test('mobile me uses native dial when advisor has staff cell phone but no telephony endpoint', function (): void {
    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    $advisor = User::factory()->create([
        'phone' => '7195558802',
    ])->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('telephony.dial_method', 'native');
});

test('advisor can send estimate link from mobile api', function (): void {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558181');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Mobile',
        'last_name' => 'Estimate',
        'phone' => '7195558181',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'vin' => '1C4HJXDG6EW123457',
        'normalized_vin' => '1C4HJXDG6EW123457',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Water pump noise',
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Water pump',
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'position' => 1,
        ])->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Water pump labor',
        'quantity' => 1,
        'unit_price' => 15000,
    ]);

    $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/send-estimate')
        ->assertOk()
        ->assertJsonStructure(['estimate_url', 'html', 'message_id']);
});

test('advisor can send inspection link from mobile api', function (): void {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195553434');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Alex',
        'last_name' => 'Driver',
        'phone' => '7195553434',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brake noise',
    ]);

    $inspection = app(\App\Ark\Operations\Inspections\EnsureInspectionAction::class)->execute($repairOrder, $advisor);

    \App\Ark\Operations\Inspections\InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => 'brakes',
        'label' => 'Rear brake pads',
        'observed_state' => \App\Ark\Operations\Inspections\InspectionObservedState::Fail->value,
        'notes' => 'Metal on metal.',
        'position' => 0,
    ]);

    $this->withToken($token)
        ->postJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/send-inspection')
        ->assertOk()
        ->assertJsonStructure(['inspection_url', 'html', 'message_id']);

    expect(\App\Ark\Operations\Portal\InspectionAccessToken::query()->count())->toBe(1);
});

test('advisor can fetch mobile inspection portal preview url', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Preview',
        'last_name' => 'Driver',
        'phone' => '7195555656',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Suspension noise',
    ]);

    $inspection = app(\App\Ark\Operations\Inspections\EnsureInspectionAction::class)->execute($repairOrder, $advisor);

    \App\Ark\Operations\Inspections\InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => 'suspension',
        'label' => 'Front strut',
        'observed_state' => \App\Ark\Operations\Inspections\InspectionObservedState::Fail->value,
        'notes' => 'Leaking.',
        'position' => 0,
    ]);

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-portal-preview')
        ->assertOk()
        ->assertJsonStructure(['url'])
        ->assertJsonPath('url', fn ($url) => is_string($url) && str_contains($url, '/portal/inspections/'));
});

test('staff can register mobile device for observability', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $token = $technician->createToken('Landon iPhone')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mobile/device', [
            'device_name' => 'Landon iPhone',
            'platform' => 'ios',
            'app_version' => '1.0.0',
        ])
        ->assertOk()
        ->assertJsonPath('device.device_name', 'Landon iPhone')
        ->assertJsonPath('device.platform', 'ios');

    $this->withToken($token)
        ->postJson('/api/mobile/device', [
            'device_name' => 'Landon iPhone',
            'platform' => 'ios',
            'app_version' => '1.0.1',
        ])
        ->assertOk()
        ->assertJsonPath('device.app_version', '1.0.1');

    expect(\App\Ark\Mobile\MobileDevice::query()->count())->toBe(1);
});

test('advisor can load mobile attention projection', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/attention')
        ->assertOk()
        ->assertJsonStructure([
            'sections',
            'total_count',
            'poll_after_seconds',
            'push_enabled',
        ]);
});

test('technician cannot load shop attention projection', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/attention')
        ->assertForbidden();
});

test('technician cannot load comms hub projection', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/comms/hub')
        ->assertForbidden();
});

test('mobile attention rows carry explainable tone and observation', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Tone',
        'last_name' => 'Pressure',
        'phone' => '7195550199',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Porsche',
        'model' => 'Cayenne',
        'vin' => '1C4HJXDG6EW900199',
        'normalized_vin' => '1C4HJXDG6EW900199',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake inspection',
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Brakes',
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'position' => 1,
        ])->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Brake labor',
        'quantity' => 1,
        'unit_price_cents' => 89300,
    ]);

    // Mirror the real write path: estimate totals (total_cents) are persisted by
    // the calculator, not by raw line inserts. Without this the estimate reads $0.
    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)
        ->ensureFinancialTotalsAreCurrent($repairOrder->fresh());

    $this->withToken($token)
        ->getJson('/api/mobile/attention')
        ->assertOk()
        ->assertJsonPath('sections.0.key', 'customer_decision')
        ->assertJsonPath('sections.0.items.0.tone', 'waiting')
        ->assertJsonPath('sections.0.items.0.observation', 'customer_decision_needed')
        ->assertJsonPath('sections.0.items.0.customer_id', $customer->id);
});

test('advisor global search returns customers and vehicles', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Search',
        'last_name' => 'Target',
        'phone' => '7195559494',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Subaru',
        'model' => 'Outback',
        'plate' => 'SRCH1',
        'vin' => '4S4BSACC3G3245678',
        'normalized_vin' => '4S4BSACC3G3245678',
    ]);

    $this->withToken($token)
        ->getJson('/api/mobile/search?q=SRCH1')
        ->assertOk()
        ->assertJsonPath('vehicles.0.id', $vehicle->id)
        ->assertJsonPath('vehicles.0.customer_id', $customer->id);

    $this->withToken($token)
        ->getJson('/api/mobile/search?q=Search+Target')
        ->assertOk()
        ->assertJsonPath('customers.0.id', $customer->id);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Search RO',
    ]);

    $this->withToken($token)
        ->getJson('/api/mobile/search?q=%23'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('repair_order_number', $repairOrder->repair_order_id);
});

test('advisor mobile schedule returns day appointments', function (): void {
    ShopSettings::current()->update(['appointments_enabled' => true]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Schedule',
        'last_name' => 'Mobile',
        'phone' => '7195558181',
    ]);

    $startsAt = now()->addHours(2)->startOfMinute();
    $appointment = \App\Ark\Operations\Appointments\Appointment::query()->create([
        'customer_id' => $customer->id,
        'advisor_user_id' => $advisor->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHour(),
        'concern' => 'Oil change',
        'status' => \App\Ark\Operations\Appointments\AppointmentStatus::Scheduled,
    ]);

    $this->withToken($token)
        ->getJson('/api/mobile/schedule?date='.$startsAt->toDateString())
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('rows.0.id', $appointment->id)
        ->assertJsonPath('rows.0.customer_id', $customer->id);
});

test('advisor can create appointment from mobile', function (): void {
    ShopSettings::current()->update(['appointments_enabled' => true]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Book',
        'last_name' => 'Mobile',
        'phone' => '7195558282',
    ]);

    $startsAt = now()->addDay()->setTime(9, 0);

    $this->withToken($token)
        ->postJson('/api/mobile/appointments', [
            'customer_id' => $customer->id,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $startsAt->copy()->addHour()->toIso8601String(),
            'concern' => 'Brake noise',
        ])
        ->assertCreated()
        ->assertJsonPath('appointment.customer_id', $customer->id)
        ->assertJsonPath('appointment.concern', 'Brake noise');
});


test('advisor can record manual payment on mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/payment', [
            'amount' => number_format($repairOrder->fresh()->balanceDue()->balanceDueCents / 100, 2, '.', ''),
            'payment_method' => 'cash',
            'reference' => 'Counter cash',
        ])
        ->assertOk()
        ->assertJsonPath('payment_status', 'paid');

    expect($repairOrder->fresh()->isPaid())->toBeTrue();
});

test('advisor can record refund on mobile after payment', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/refund', [
            'amount' => '50.00',
            'reference' => 'Counter refund',
        ])
        ->assertOk()
        ->assertJsonPath('balance_due_cents', 5000);

    expect($repairOrder->fresh()->balanceDue()->balanceDueCents)->toBe(5000);

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('workspace.manual_refund.can_record', true)
        ->assertJsonPath('workspace.command_bar', fn ($commands) => collect($commands)->contains(
            fn ($command) => ($command['key'] ?? null) === 'record_refund'
        ));
});

test('admin can void ledger payment on mobile', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $token = $admin->createToken('test')->plainTextToken;

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $entry = \App\Ark\Operations\Financial\RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', \App\Ark\Operations\Financial\LedgerEntryType::Payment)
        ->firstOrFail();

    $this->withToken($token)
        ->deleteJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/ledger-entries/'.$entry->id)
        ->assertOk()
        ->assertJsonPath('balance_due_cents', 15000);

    expect($entry->fresh()->voided_at)->not->toBeNull();

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('workspace.ledger', null);
});

test('advisor can update appointment status on mobile', function (): void {
    ShopSettings::current()->update(['appointments_enabled' => true]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Status',
        'last_name' => 'Mobile',
        'phone' => '7195558383',
    ]);

    $startsAt = now()->addHours(3)->startOfMinute();
    $appointment = \App\Ark\Operations\Appointments\Appointment::query()->create([
        'customer_id' => $customer->id,
        'advisor_user_id' => $advisor->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHour(),
        'concern' => 'State inspection',
        'status' => \App\Ark\Operations\Appointments\AppointmentStatus::Scheduled,
    ]);

    $this->withToken($token)
        ->patchJson('/api/mobile/appointments/'.$appointment->id.'/status', [
            'status' => \App\Ark\Operations\Appointments\AppointmentStatus::Arrived->value,
        ])
        ->assertOk()
        ->assertJsonPath('appointment.status', 'arrived')
        ->assertJsonPath('appointment.status_label', 'Checked in');
});

test('admin can load owner bookend on mobile', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/owner/bookend')
        ->assertOk()
        ->assertJsonStructure([
            'bookend' => [
                'range_label',
                'shop_date',
                'reconciliation' => ['reconciles', 'sales_posted', 'cash_collected', 'delta_label'],
                'sales_effectiveness',
                'shop_metrics',
                'priorities',
                'target_review' => ['stale', 'last_review'],
                'poll_after_seconds',
            ],
        ])
        ->assertJsonPath('bookend.priorities.0.label', 'Waiting approval');

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('capabilities.owner_bookend', true);
});

test('advisor cannot load owner bookend on mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/owner/bookend')
        ->assertForbidden();

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('capabilities.owner_bookend', false);
});

test('admin can load owner operational report on mobile', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/owner/operational-report')
        ->assertOk()
        ->assertJsonStructure([
            'report' => [
                'range_label',
                'from_date',
                'to_date',
                'tabs',
                'pulse' => ['kpis'],
                'margin' => ['rows'],
                'financial' => [
                    'mix_rows',
                    'reconciliation' => [
                        'reconciles',
                        'delta_label',
                        'posted_ro_summary',
                        'rows',
                    ],
                ],
                'owner_pl' => [
                    'pl_lines',
                    'tax_lines',
                    'benchmark',
                    'disclaimer',
                ],
                'production' => [
                    'pressure',
                    'advisors',
                    'technicians',
                ],
                'poll_after_seconds',
            ],
        ])
        ->assertJsonPath('report.pulse.kpis.0.label', 'Sales Posted');

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('capabilities.owner_operational_report', true);
});

test('advisor cannot load owner operational report on mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/owner/operational-report')
        ->assertForbidden();
});

test('advisor can record manual deposit on mobile before invoice', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $repairOrder = financialCloseoutRepairOrder();

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/deposit', [
            'amount' => '50.00',
            'payment_method' => 'cash',
            'reference' => 'Counter cash',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Deposit recorded.');

    expect(\App\Ark\Operations\Financial\RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', \App\Ark\Operations\Financial\LedgerEntryType::Deposit)
        ->exists())->toBeTrue();
});


test('advisor can load portable station orientation home', function (): void {
    $advisor = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/orientation')
        ->assertOk()
        ->assertJsonStructure([
            'home_profile',
            'current_situation',
            'context',
            'next_best_action',
            'confidence',
            'actions',
            'ownership',
            'pressure',
            'items',
            'attention_total',
            'continuity',
            'continuity_badge',
            'poll_after_seconds',
            'push_enabled',
        ])
        ->assertJsonPath('home_profile', 'advisor')
        ->assertJsonPath('context', 'Who needs a response or decision?');
});

test('technician orientation home projects assigned work items', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $token = $technician->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/orientation')
        ->assertOk()
        ->assertJsonPath('home_profile', 'technician')
        ->assertJsonPath('items.0.repair_order_id', $repairOrder->repair_order_id)
        ->assertJsonPath('items.0.deep_link', 'repair_order');
});

test('advisor can mark conversation read on mobile', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Read',
        'last_name' => 'Customer',
        'phone' => '7195558800',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558800',
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Need an update',
        'occurred_at' => now(),
    ]);

    $this->withToken($token)
        ->postJson('/api/mobile/conversations/'.$conversation->id.'/read')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('unread_count', 0);
});

test('mobile conversation thread exposes attachment urls for mms', function (): void {
    Storage::fake('local');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Photo',
        'last_name' => 'Customer',
        'phone' => '7195558811',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558811',
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    $message = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => '',
        'occurred_at' => now(),
    ]);

    Storage::disk('local')->put('mms/test-photo.jpg', 'jpeg-bytes');

    $attachment = \App\Ark\Operations\Conversations\ConversationMessageAttachment::query()->create([
        'conversation_message_id' => $message->id,
        'content_type' => 'image/jpeg',
        'storage_path' => 'mms/test-photo.jpg',
        'byte_size' => 10,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/mobile/conversations/'.$conversation->id)
        ->assertOk();

    $events = $response->json('thread.events');
    expect($events)->not->toBeEmpty();
    expect($events[0]['metadata']['attachments'][0]['url'] ?? null)->not->toBeNull();

    $this->withToken($token)
        ->get($events[0]['metadata']['attachments'][0]['url'])
        ->assertOk();
});

test('staff can register fcm token on mobile device', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('Advisor iPhone')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mobile/device', [
            'device_name' => 'Advisor iPhone',
            'platform' => 'ios',
            'app_version' => '1.2.0',
            'fcm_token' => 'fcm-test-token-abc123',
        ])
        ->assertOk()
        ->assertJsonPath('device.push_registered', true);

    expect(\App\Ark\Mobile\MobileDevice::query()->value('fcm_token'))->toBe('fcm-test-token-abc123');
});

test('mobile device registration reports push enabled from shop settings', function (): void {
    ShopSettings::current()->persistTrusted([
        'mobile_push' => [
            'enabled' => true,
            'firebase_project_id' => 'ark-mobile-test',
        ],
        'mobile_push_firebase_service_account' => json_encode([
            'type' => 'service_account',
            'client_email' => 'fcm@test.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n",
        ], JSON_THROW_ON_ERROR),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('Advisor iPhone')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mobile/device', [
            'device_name' => 'Advisor iPhone',
            'platform' => 'ios',
        ])
        ->assertOk()
        ->assertJsonPath('push_enabled', true);
});

test('technician can load inspection checklist and mark items with tap statuses', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $token = $technician->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-checklist');

    $response->assertOk()
        ->assertJsonPath('checklist.template_applied', true)
        ->assertJsonStructure([
            'checklist' => [
                'progress' => ['checked', 'total'],
                'status_options',
                'categories' => [
                    ['name', 'items' => [['id', 'label', 'status', 'checked']]],
                ],
            ],
        ]);

    expect($response->json('checklist.progress.total'))->toBeGreaterThan(20);

    $firstItemId = $response->json('checklist.categories.0.items.0.id');

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-checklist/items/'.$firstItemId, [
            'status' => 'good',
        ])
        ->assertOk()
        ->assertJsonPath('item.status', 'good')
        ->assertJsonPath('item.status_label', 'Good')
        ->assertJsonPath('item.checked', true);

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-checklist/items/'.$firstItemId, [
            'status' => 'needs_attention',
            'note' => 'Pad material low',
        ])
        ->assertOk()
        ->assertJsonPath('item.status', 'needs_attention')
        ->assertJsonPath('item.note', 'Pad material low')
        ->assertJsonPath('item.recommendation_hint.available', false);
});

test('technician can load inspection item living record with evidence and recommendation hint', function (): void {
    Storage::fake('local');

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $token = $technician->createToken('test')->plainTextToken;

    $checklist = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-checklist')
        ->assertOk()
        ->json('checklist');

    $itemId = $checklist['categories'][0]['items'][0]['id'];

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-checklist/items/'.$itemId)
        ->assertOk()
        ->assertJsonStructure([
            'item' => [
                'id',
                'label',
                'category_name',
                'status_display',
                'photos',
                'measurements',
                'recommendation_hint',
                'prior_visits',
                'navigation',
            ],
        ])
        ->assertJsonPath('item.prior_visits.empty_label', 'No prior history for this inspection point yet.');

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-checklist/items/'.$itemId, [
            'status' => 'needs_attention',
            'note' => 'Pad material low',
            'measurement_value' => '1',
            'measurement_unit' => 'mm',
        ])
        ->assertOk()
        ->assertJsonPath('item.status_display', 'Needs Attention')
        ->assertJsonPath('item.recommendation_hint.available', true)
        ->assertJsonPath('item.recommendation_hint.label', 'Recommendation draft available')
        ->assertJsonPath('item.measurements.0.formatted', '1 mm')
        ->assertJsonPath('item.navigation.next_item_id', fn ($value) => $value !== null);
});

test('technician can attach video evidence through checklist update', function (): void {
    Storage::fake('local');

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = mobileRepairOrder($technician);
    $token = $technician->createToken('test')->plainTextToken;

    $itemId = $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-checklist')
        ->assertOk()
        ->json('checklist.categories.0.items.0.id');

    $this->withToken($token)
        ->patch('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/inspection-checklist/items/'.$itemId, [
            'status' => 'failed',
            'photo' => UploadedFile::fake()->create('brake-noise.mp4', 256, 'video/mp4'),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('item.photos.0.is_video', true)
        ->assertJsonPath('item.photos.0.content_type', 'video/mp4');
});

test('advisor customer workspace exposes money, open work, and quick actions', function (): void {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195559191');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Workspace',
        'last_name' => 'Depth',
        'phone' => '7195559191',
        'customer_type' => 'Retail',
    ]);

    $vehicleA = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
        'vin' => '1FTEW1EP5KFA12345',
        'normalized_vin' => '1FTEW1EP5KFA12345',
    ]);

    $vehicleB = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '19XFC2F59FE123456',
        'normalized_vin' => '19XFC2F59FE123456',
    ]);

    $openB = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicleB->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Oil leak',
    ]);

    $openA = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicleA->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake noise',
    ]);

    $concernA = RepairOrderConcern::query()->create([
        'repair_order_id' => $openA->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $openA->lines()->create([
        'repair_order_concern_id' => $concernA->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Brake labor',
        'quantity' => 1,
        'unit_price' => 12000,
    ]);

    $inspection = app(\App\Ark\Operations\Inspections\EnsureInspectionAction::class)->execute($openA, $advisor);

    \App\Ark\Operations\Inspections\InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => 'brakes',
        'label' => 'Front pads',
        'observed_state' => \App\Ark\Operations\Inspections\InspectionObservedState::Fail->value,
        'notes' => 'Worn.',
        'position' => 0,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/mobile/customers/'.$customer->id)
        ->assertOk()
        ->assertJsonStructure([
            'customer',
            'orientation',
            'blocks',
            'quick_actions',
            'repair_orders',
        ]);

    $response->assertJsonPath('quick_actions.0.key', 'text_customer');

    $quickActionKeys = collect($response->json('quick_actions'))->pluck('key')->all();
    expect($quickActionKeys)->toContain('send_estimate', 'send_inspection');

    $openWorkBlock = collect($response->json('blocks'))->firstWhere('key', 'open_work');
    expect($openWorkBlock)->not->toBeNull()
        ->and($openWorkBlock['payload']['items'] ?? [])->toHaveCount(2);

    $repairOrderRows = collect($response->json('repair_orders'));
    expect($repairOrderRows->firstWhere('repair_order_id', $openA->repair_order_id)['estimate_total_label'] ?? null)
        ->not->toBeNull();

    $this->withToken($token)
        ->postJson('/api/mobile/customers/'.$customer->id.'/messages', [
            'body' => 'Hi — your estimate is ready when you are.',
            'repair_order_id' => $openA->repair_order_id,
        ])
        ->assertCreated()
        ->assertJsonStructure(['conversation_id', 'message_id']);
});

test('technician cannot open customer workspace on mobile', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $token = $technician->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Tech',
        'last_name' => 'Customer',
        'phone' => '7195559292',
    ]);

    $this->withToken($token)
        ->getJson('/api/mobile/customers/'.$customer->id)
        ->assertForbidden();
});

test('advisor vehicle workspace exposes open work, history, and deferred concerns', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $token = $advisor->createToken('test')->plainTextToken;

    $customer = Customer::query()->create([
        'first_name' => 'Vehicle',
        'last_name' => 'Workspace',
        'phone' => '7195559393',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'vin' => '1C4HJXDG6EW123458',
        'normalized_vin' => '1C4HJXDG6EW123458',
    ]);

    $open = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Coolant leak',
        'mileage_in' => 84210,
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $open->id,
        'summary' => 'Radiator service',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $open->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Radiator R&R',
        'quantity' => 1,
        'unit_price' => 18000,
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Oil change',
        'closed_at' => now()->subMonths(2),
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/mobile/vehicles/'.$vehicle->id)
        ->assertOk()
        ->assertJsonStructure([
            'vehicle',
            'customer',
            'orientation',
            'open_repair_orders',
            'service_history',
            'deferred_work',
            'quick_actions',
        ]);

    expect($response->json('open_repair_orders'))->toHaveCount(1)
        ->and($response->json('service_history'))->toHaveCount(1)
        ->and($response->json('deferred_work.0.title'))->toBe('Radiator service')
        ->and($response->json('open_repair_orders.0.estimate_total_label'))->not->toBeNull()
        ->and(collect($response->json('quick_actions'))->pluck('key')->all())
        ->toContain('open_repair_order', 'text_customer', 'start_ro');
});

function mobileRepairOrder(
    ?User $assignedTechnician = null,
    RepairOrderConcernDisposition $disposition = RepairOrderConcernDisposition::Draft,
): RepairOrder {
    $customer = Customer::query()->create([
        'first_name' => 'Mobile',
        'last_name' => 'Customer',
        'phone' => '7195552000',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'MOB1',
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
        'vin' => '4T1B11HK1KU123456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brake noise on stop.',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise on stop',
        'customer_states' => 'Customer states brakes squeal when stopping.',
        'disposition' => $disposition->value,
        'position' => 0,
    ]);

    if ($assignedTechnician !== null) {
        $repairOrder->forceFill(['assigned_technician_id' => $assignedTechnician->id])->save();

        $concern = $repairOrder->fresh()->concerns->first();
        RepairOrderWorkGroup::query()->create([
            'repair_order_concern_id' => $concern->id,
            'title' => 'Diagnosis',
            'position' => 1,
            'owner_type' => RepairActionOwnerType::Technician,
            'owner_user_id' => $assignedTechnician->id,
        ]);
    }

    return $repairOrder->fresh(['concerns']);
}
