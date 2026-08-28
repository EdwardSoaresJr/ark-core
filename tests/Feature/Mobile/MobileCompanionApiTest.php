<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('login includes companion shell navigation for advisor', function (): void {
    $advisor = User::factory()->create(['password' => 'password'])
        ->assignRole(ArkRole::Advisor->value);

    $response = $this->postJson('/api/mobile/auth/login', [
        'email' => $advisor->email,
        'password' => 'password',
        'device_name' => 'Razr',
    ]);

    $response->assertOk()
        ->assertJsonPath('companion.product', 'companion_v1')
        ->assertJsonPath('companion.default_home_route', 'comms')
        ->assertJsonStructure(['companion' => ['phone_status_label']]);

    expect($response->json('companion.phone_status_label'))->toBeString()->not->toBeEmpty();

    $navKeys = collect($response->json('companion.navigation'))->pluck('key')->all();

    expect($navKeys)->toBe(['home', 'comms', 'search', 'schedule', 'more']);
});

test('login rejects staff without mobile access role', function (): void {
    $user = User::factory()->create([
        'password' => 'password',
        'is_active' => true,
    ]);

    $this->postJson('/api/mobile/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Razr',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('login includes technician companion shell with my work only', function (): void {
    $tech = User::factory()->create(['password' => 'password'])
        ->assignRole(ArkRole::Technician->value);

    $response = $this->postJson('/api/mobile/auth/login', [
        'email' => $tech->email,
        'password' => 'password',
        'device_name' => 'Bay tablet',
    ]);

    $response->assertOk()
        ->assertJsonPath('home_profile', 'technician')
        ->assertJsonPath('companion.default_home_route', 'my_work')
        ->assertJsonPath('capabilities.mobile', true);

    $navKeys = collect($response->json('companion.navigation'))->pluck('key')->all();

    expect($navKeys)->toBe(['my_work', 'more']);
});

test('incoming call context resolves customer vehicle and repair order from phone', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Emma',
        'last_name' => 'Hathorn',
        'phone' => '5125550199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'plate' => 'ABC123',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake noise',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise',
        'customer_states' => 'Squeal when stopping',
        'disposition' => RepairOrderConcernDisposition::Draft->value,
        'position' => 0,
    ]);

    $token = $advisor->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/mobile/incoming-call/context?phone=5125550199');

    $response->assertOk()
        ->assertJsonPath('matched', true)
        ->assertJsonPath('customer.id', $customer->id)
        ->assertJsonPath('vehicle.label', $vehicle->display_name)
        ->assertJsonPath('repair_order.repair_order_id', $repairOrder->repair_order_id)
        ->assertJsonPath('routes.repair_order', 'companion://repair-orders/'.$repairOrder->repair_order_id);
});

test('incoming call context accepts call session id', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Caller',
        'phone' => '5125550101',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcompanion001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+15125550101',
        'to_number' => '+17195559999',
        'normalized_from' => '5125550101',
        'status' => CallSessionStatus::Ringing,
        'customer_id' => $customer->id,
        'started_at' => now(),
    ]);

    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/incoming-call/context?call_session_id='.$session->id)
        ->assertOk()
        ->assertJsonPath('call_session_id', $session->id)
        ->assertJsonPath('customer.id', $customer->id);
});

test('calls library returns automotive rows with companion deep links', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Missed',
        'last_name' => 'Caller',
        'phone' => '5125550111',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Oil leak',
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmissed001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+15125550111',
        'to_number' => '+17195559999',
        'normalized_from' => '5125550111',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $customer->id,
        'repair_order_id' => $repairOrder->id,
        'started_at' => now(),
    ]);

    $token = $advisor->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/mobile/calls?filter=missed');

    $response->assertOk()
        ->assertJsonStructure([
            'items' => [
                ['headline', 'vehicle_label', 'repair_order_id', 'deep_link', 'routes'],
            ],
            'counts',
            'pagination',
        ]);

    expect($response->json('items.0.deep_link'))->toStartWith('companion://calls/');
});

test('conversations index exposes advisor awareness fields for inbox', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '5125550200',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake approval',
    ]);

    $conversation = \App\Ark\Operations\Conversations\Conversation::query()->create([
        'contact_surface' => \App\Ark\Operations\Conversations\ConversationContactSurface::Phone,
        'contact_address' => '5125550200',
        'status' => \App\Ark\Operations\Conversations\ConversationStatus::Open,
        'waiting_on' => \App\Ark\Operations\Conversations\ConversationWaitingOn::Customer,
        'owned_by_user_id' => $advisor->id,
    ]);
    \App\Ark\Operations\Conversations\ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => \App\Ark\Operations\Conversations\ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);
    \App\Ark\Operations\Conversations\ConversationLink::query()->create([
        'conversation_id' => $conversation->id,
        'linkable_type' => (new RepairOrder)->getMorphClass(),
        'linkable_id' => $repairOrder->id,
    ]);

    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/conversations')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [[
                'id',
                'headline',
                'posture_label',
                'vehicle_label',
                'repair_order_id',
                'lifecycle_label',
                'assigned_label',
                'unread_count',
                'needs_attention',
                'deep_link',
            ]],
            'count',
            'poll_after_seconds',
        ]);
});

test('mobile calls mark handled', function () {
    $advisor = User::factory()->create(['password' => 'password'])
        ->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Handled',
        'last_name' => 'Caller',
        'phone' => '5125550112',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brakes',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmissed002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+15125550112',
        'to_number' => '+17195559999',
        'normalized_from' => '5125550112',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $customer->id,
        'repair_order_id' => $repairOrder->id,
        'started_at' => now(),
    ]);

    $token = $advisor->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/mobile/calls/{$session->id}/mark-handled");

    $response->assertOk()
        ->assertJsonPath('handled', true)
        ->assertJsonPath('call_session_id', $session->id);

    expect($session->fresh()->worked_at)->not->toBeNull();
});
