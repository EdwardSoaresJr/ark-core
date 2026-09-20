<?php

use App\Ark\Dragon\Agent\DragonAgentConversation;
use App\Ark\Dragon\Agent\DragonModelTurn;
use App\Ark\Dragon\Agent\Providers\FakeDragonProvider;
use App\Ark\Dragon\Agent\Tools\AdvisorTasksQueryTool;
use App\Ark\Dragon\DragonServiceToken;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Work\AdvisorTask;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Station\StationDeviceToken;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['shop.identity' => 'test.demo-auto.local']);
    config(['shop.dragon_work_items_limit' => 50]);
});

function stationOpenRo(RepairOrderStatus $status): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Secret',
        'last_name' => 'Customer',
        'phone' => '7195559999',
        'email' => 'secret@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'STN1',
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Noise on braking.',
        'assigned_technician_id' => null,
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Noise on braking',
        'disposition' => RepairOrderConcernDisposition::Recommended->value,
        'position' => 0,
    ]);

    return $repairOrder->fresh();
}

test('station device token loads dashboard with customer and vehicle, without contact PII', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    stationOpenRo(RepairOrderStatus::WaitingApproval);
    stationOpenRo(RepairOrderStatus::InProgress);

    $response = $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk()
        ->assertJsonPath('surface', 'advisor_station')
        ->assertJsonPath('privacy', 'shop_floor')
        ->assertJsonPath('health.ark', 'ok')
        ->assertJsonPath('calls.ready', true)
        ->assertJsonPath('desk.pressure.waiting_approval_count', 1)
        ->assertJsonPath('desk.pressure.rows.0.customer_label', 'Secret Customer')
        ->assertJsonPath('dragon.ready', false)
        ->assertJsonMissing(['7195559999', 'secret@example.test']);

    expect($response->json('today.waiting_approval.0.customer_label'))->toBe('Secret Customer');
    expect($response->json('today.waiting_approval.0.vehicle_label'))->toContain('Toyota');
});

test('station dashboard rejects unauthenticated callers', function (): void {
    $this->getJson('/api/station/dashboard')->assertUnauthorized();
});

test('station glass excludes inactive advisors from assignment choices', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    User::factory()->create(['name' => 'Active Advisor', 'is_active' => true])
        ->assignRole(ArkRole::Advisor->value);
    User::factory()->create(['name' => 'Disabled Advisor', 'is_active' => false])
        ->assignRole(ArkRole::Advisor->value);

    $names = $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk()
        ->collect('glass.eligible_advisors')
        ->pluck('name');

    expect($names)
        ->toContain('Active Advisor')
        ->not->toContain('Disabled Advisor');
});

test('station dashboard rejects staff Sanctum tokens', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->withToken($advisor->createToken('advisor-station')->plainTextToken)
        ->getJson('/api/station/dashboard')
        ->assertUnauthorized();
});

test('station dashboard rejects Dragon machine tokens', function (): void {
    $issued = DragonServiceToken::issue('test-dragon', 'test.demo-auto.local');

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertUnauthorized();
});

test('station repair order show uses ARK open-work card', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $ro = stationOpenRo(RepairOrderStatus::InProgress);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/repair-orders/'.$ro->repair_order_id)
        ->assertOk()
        ->assertJsonPath('repair_order.repair_order_id', $ro->repair_order_id)
        ->assertJsonPath('repair_order.customer_label', 'Secret Customer')
        ->assertJsonMissing(['7195559999', 'secret@example.test']);
});

test('station API rejects writes', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dashboard')
        ->assertStatus(405);
});

test('station device token cannot hit Dragon or staff mobile routes', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $ro = stationOpenRo(RepairOrderStatus::InProgress);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/dragon/me')
        ->assertNotFound();

    $this->withToken($issued['plain_text'])
        ->getJson('/api/dragon/work')
        ->assertNotFound();

    $query = $this->withToken($issued['plain_text'])
        ->postJson('/api/dragon/query', ['entity' => 'repair_orders', 'limit' => 1, 'filters' => []]);
    expect($query->status())->toBeIn([404, 405]);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/mobile/me')
        ->assertUnauthorized();

    $this->withToken($issued['plain_text'])
        ->getJson('/api/mobile/work')
        ->assertUnauthorized();

    $this->withToken($issued['plain_text'])
        ->patchJson('/api/mobile/repair-orders/'.$ro->id.'/status', ['status' => 'completed'])
        ->assertUnauthorized();

    $this->withToken($issued['plain_text'])
        ->postJson('/api/mobile/auth/logout')
        ->assertUnauthorized();
});

test('revoked station device token is rejected immediately', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $issued['token']->revoke();

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertUnauthorized();
});

test('station device token is tenant isolated by shop identity', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'other-shop.example');

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertUnauthorized();
});

test('station last_used_at updates without leaking the plaintext token', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-22 19:05:00'));
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    expect($issued['token']->last_used_at)->toBeNull();

    $response = $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk();

    $body = $response->getContent();
    expect($body)->not->toContain($issued['plain_text'])
        ->and($body)->not->toContain($issued['token']->token_hash)
        ->and($response->json())->not->toHaveKeys(['token', 'token_hash', 'plain_text', 'token_prefix']);

    $issued['token']->refresh();
    expect($issued['token']->last_used_at?->equalTo(Carbon::parse('2026-08-22 19:05:00')))->toBeTrue();

    Carbon::setTestNow();
});

test('station me returns device name without token secrets', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $response = $this->withToken($issued['plain_text'])
        ->getJson('/api/station/me')
        ->assertOk()
        ->assertJsonPath('surface', 'advisor_station')
        ->assertJsonPath('shop_identity', 'test.demo-auto.local')
        ->assertJsonPath('station.name', 'front-counter-glass')
        ->assertJsonMissingPath('token_hash')
        ->assertJsonMissingPath('token_prefix');

    expect($response->getContent())->not->toContain($issued['plain_text']);
});

test('station token can start a hosted Dragon conversation without a staff PAT', function (): void {
    config(['dragon.provider' => 'fake']);
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn('Waiting approval is the shop pressure. Open those ROs in ARK.', []),
    ];

    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', ['message' => 'What needs attention?'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('reply', 'Waiting approval is the shop pressure. Open those ROs in ARK.')
        ->assertJsonStructure(['conversation_id']);
});

test('station token cannot post the dashboard', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dashboard', ['message' => 'no'])
        ->assertStatus(405);
});

test('staff Sanctum cannot use the station Dragon chat route', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->withToken($advisor->createToken('desk')->plainTextToken)
        ->postJson('/api/station/dragon/chat', ['message' => 'What needs attention?'])
        ->assertUnauthorized();
});

test('station Dragon conversation_id continues the same hosted thread', function (): void {
    config(['dragon.provider' => 'fake']);
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn('Waiting approval is first.', []),
        new DragonModelTurn('Still waiting approval. Open RO 1597 in ARK.', []),
    ];

    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $first = $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', ['message' => 'What needs attention?'])
        ->assertOk()
        ->json();

    $conversationId = $first['conversation_id'];
    expect($conversationId)->toBeString();

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', [
            'message' => 'Which RO should we open?',
            'conversation_id' => $conversationId,
        ])
        ->assertOk()
        ->assertJsonPath('conversation_id', $conversationId)
        ->assertJsonPath('reply', 'Still waiting approval. Open RO 1597 in ARK.');

    $conversation = DragonAgentConversation::query()->where('uuid', $conversationId)->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->user_id)->toBeNull()
        ->and($conversation->station_device_token_id)->toBe($issued['token']->id)
        ->and($conversation->messages()->count())->toBe(4);
});

test('station Dragon keeps the live Glass thread even without conversation_id', function (): void {
    config(['dragon.provider' => 'fake']);
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn('Priority is the Silverado on the board.', []),
        new DragonModelTurn('The Silverado is still the one you named.', []),
    ];

    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $first = $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', ['message' => 'Treat the Silverado as the priority today.'])
        ->assertOk()
        ->json();

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', ['message' => 'What did I just tell you to treat as the priority?'])
        ->assertOk()
        ->assertJsonPath('conversation_id', $first['conversation_id']);

    $second = $fake->receivedMessages[1]['messages'] ?? [];
    $contents = collect($second)->pluck('content')->implode("\n");
    expect($contents)->toContain('Treat the Silverado as the priority today.')
        ->and($contents)->toContain('What did I just tell you to treat as the priority?')
        ->and($contents)->not->toContain('[Shared Shop Glass.');
});

test('dragon service principal cannot use station Ask Dragon', function (): void {
    $issued = DragonServiceToken::issue('test-dragon', 'test.demo-auto.local');

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', ['message' => 'What needs attention?'])
        ->assertUnauthorized();
});

test('Dragon unavailable returns 503 and the glass dashboard still loads', function (): void {
    config(['dragon.provider' => 'fake']);
    $fake = app(FakeDragonProvider::class);
    $fake->unavailable = true;

    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', ['message' => 'What needs attention?'])
        ->assertStatus(503)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'provider_unavailable');

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk()
        ->assertJsonPath('surface', 'advisor_station');
});

test('station Ask Dragon can answer an open-RO count without a staff PAT', function (): void {
    stationOpenRo(RepairOrderStatus::InProgress);
    stationOpenRo(RepairOrderStatus::WaitingApproval);

    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', ['message' => 'How many repair orders are open?'])
        ->assertOk()
        ->assertJsonPath('source', 'fast_fact')
        ->assertJsonPath('reply', 'There are 2 open repair orders.');
});

test('station dashboard includes live CallSession rows', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $ro = stationOpenRo(RepairOrderStatus::InProgress);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAglass001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195550000',
        'normalized_to' => '7195559999',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $ro->customer_id,
        'repair_order_id' => $ro->id,
        'started_at' => now()->subMinutes(8),
        'ended_at' => now()->subMinutes(7),
    ]);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk()
        ->assertJsonPath('calls.ready', true)
        ->assertJsonPath('calls.recent.0.repair_order_id', $ro->repair_order_id)
        ->assertJsonMissing(['TwilioPG']);
});

test('station glass call show resolves customer and open RO context', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $ro = stationOpenRo(RepairOrderStatus::WaitingApproval);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAglass-show-1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195550000',
        'normalized_to' => '7195559999',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $ro->customer_id,
        'repair_order_id' => $ro->id,
        'started_at' => now()->subMinutes(8),
        'ended_at' => now()->subMinutes(7),
    ]);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/calls/'.$session->id)
        ->assertOk()
        ->assertJsonPath('call.id', $session->id)
        ->assertJsonPath('call.repair_order_id', $ro->repair_order_id)
        ->assertJsonPath('call.context.customer.id', $ro->customer_id);
});

test('desk projects unclaimed missed calls into shared work', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $ro = stationOpenRo(RepairOrderStatus::WaitingApproval);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAglass-desk-missed',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195550000',
        'normalized_to' => '7195559999',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $ro->customer_id,
        'repair_order_id' => $ro->id,
        'started_at' => now()->subMinutes(8),
        'ended_at' => now()->subMinutes(7),
    ]);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk()
        ->assertJsonPath('desk.shared.0.kind', 'missed_call')
        ->assertJsonPath('desk.shared.0.call_session_id', CallSession::query()->latest('id')->value('id'));
});

test('return-call task does not mark the missed call handled', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $ro = stationOpenRo(RepairOrderStatus::WaitingApproval);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAglass-return-1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195550000',
        'normalized_to' => '7195559999',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $ro->customer_id,
        'repair_order_id' => $ro->id,
        'started_at' => now()->subMinutes(8),
        'ended_at' => now()->subMinutes(7),
    ]);

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/tasks', [
            'title' => 'Return Secret Customer call',
            'assigned_user_id' => $advisor->id,
            'call_session_id' => $session->id,
            'repair_order_id' => $ro->repair_order_id,
        ])
        ->assertOk();

    expect($session->fresh()->worked_at)->toBeNull();
});

test('handled missed call sets worked_at and leaves the desk', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $ro = stationOpenRo(RepairOrderStatus::WaitingApproval);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAglass-handled-1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195550000',
        'normalized_to' => '7195559999',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $ro->customer_id,
        'repair_order_id' => $ro->id,
        'started_at' => now()->subMinutes(8),
        'ended_at' => now()->subMinutes(7),
    ]);

    AdvisorTask::query()->create([
        'created_by_user_id' => $advisor->id,
        'assigned_user_id' => $advisor->id,
        'call_session_id' => $session->id,
        'repair_order_id' => $ro->id,
        'notes' => 'Return Secret Customer call',
        'due_at' => now()->addHour(),
    ]);

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/calls/'.$session->id.'/handled')
        ->assertOk();

    expect($session->fresh()->worked_at)->not->toBeNull();
    expect(AdvisorTask::query()->where('call_session_id', $session->id)->whereNull('completed_at')->count())->toBe(0);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk()
        ->assertJsonPath('calls.missed', []);
});

test('unknown caller call show does not invent a customer', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAglass-unknown-1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17190001111',
        'to_number' => '+17195559999',
        'normalized_from' => '7190001111',
        'normalized_to' => '7195559999',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinutes(3),
        'ended_at' => now()->subMinutes(2),
    ]);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/calls/'.$session->id)
        ->assertOk()
        ->assertJsonPath('call.id', $session->id)
        ->assertJsonPath('call.context.customer', null)
        ->assertJsonPath('call.repair_order_id', null);
});

test('station glass settings store advisor mode on the device token', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/settings', [
            'appearance' => 'light',
            'advisor_mode' => 'one',
            'primary_advisor_user_id' => $advisor->id,
        ])
        ->assertOk()
        ->assertJsonPath('glass.advisor_mode', 'one')
        ->assertJsonPath('glass.primary_advisor_user_id', $advisor->id);
});

test('completing an advisor task does not change repair order status', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $ro = stationOpenRo(RepairOrderStatus::WaitingApproval);

    $task = AdvisorTask::query()->create([
        'created_by_user_id' => $advisor->id,
        'assigned_user_id' => $advisor->id,
        'repair_order_id' => $ro->id,
        'notes' => 'Follow up RO '.$ro->repair_order_id,
        'due_at' => now()->addHour(),
    ]);

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/tasks/'.$task->id.'/complete')
        ->assertOk();

    expect($task->fresh()->completed_at)->not->toBeNull();
    expect($ro->fresh()->status->value)->toBe(RepairOrderStatus::WaitingApproval->value);
});

test('advisor_tasks.query tool is read only', function (): void {
    $tool = app(AdvisorTasksQueryTool::class);
    $result = $tool->invoke([]);

    expect($result['read_only'])->toBeTrue()
        ->and($result)->not->toHaveKey('writes');
});
