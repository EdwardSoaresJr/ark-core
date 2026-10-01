<?php

use App\Ark\Dragon\Agent\DragonModelTurn;
use App\Ark\Dragon\Agent\Providers\FakeDragonProvider;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Work\AdvisorTask;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['shop.identity' => 'test.demo-auto.local']);
    config(['dragon.provider' => 'fake']);
});

function deskAdvisor(string $name = 'Molly Advisor'): array
{
    $user = User::factory()->create(['name' => $name])->assignRole(ArkRole::Advisor->value);

    return [$user, $user->createToken('ARK Desk')->plainTextToken];
}

function deskCall(?User $owner, ?Customer $customer, CallSessionStatus $status = CallSessionStatus::Missed, string $from = '7195550123'): CallSession
{
    return CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CA'.uniqid(),
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+1'.$from,
        'to_number' => '+17195559999',
        'normalized_from' => $from,
        'status' => $status,
        'customer_id' => $customer?->id,
        'owned_by_user_id' => $owner?->id,
        'started_at' => Carbon::now()->subMinutes(5),
    ]);
}

test('desk login issues a staff sanctum token without a station token', function (): void {
    $user = User::factory()->create([
        'email' => 'molly@example.test',
        'password' => bcrypt('secret'),
    ])->assignRole(ArkRole::Advisor->value);

    $this->postJson('/api/desk/auth/login', [
        'email' => 'molly@example.test',
        'password' => 'secret',
        'device_name' => 'Molly PC',
    ])
        ->assertOk()
        ->assertJsonPath('product', 'ark_desk')
        ->assertJsonPath('user.name', $user->name)
        ->assertJsonStructure(['token', 'shop' => ['name', 'host', 'origin']]);
});

test('desk login rejects technicians and station tokens are not required', function (): void {
    $tech = User::factory()->create([
        'email' => 'tech@example.test',
        'password' => bcrypt('secret'),
    ])->assignRole(ArkRole::Technician->value);

    $this->postJson('/api/desk/auth/login', [
        'email' => 'tech@example.test',
        'password' => 'secret',
    ])->assertStatus(422);

    $this->getJson('/api/desk/work')->assertUnauthorized();
});

test('desk work is personal and does not include another advisor owned call', function (): void {
    [$molly] = deskAdvisor('Molly');
    [$edward] = deskAdvisor('Edward');
    $customer = Customer::query()->create(['first_name' => 'Alyssa', 'last_name' => 'Stasiak', 'phone' => '7195550123']);
    deskCall($molly, $customer, CallSessionStatus::Missed, '7195550123');
    deskCall($edward, $customer, CallSessionStatus::Ringing, '7195550456');

    Sanctum::actingAs($molly);
    $mollyWork = $this->getJson('/api/desk/work')->assertOk()->json();
    Sanctum::actingAs($edward);
    $edwardWork = $this->getJson('/api/desk/work')->assertOk()->json();

    expect($mollyWork['surface'])->toBe('ark_desk')
        ->and(collect($mollyWork['my_work'])->pluck('call_session_id')->filter()->all())->toBe(
            CallSession::query()->where('owned_by_user_id', $molly->id)->pluck('id')->all()
        )
        ->and(collect($edwardWork['my_work'])->pluck('call_session_id')->filter()->all())->toBe(
            CallSession::query()->where('owned_by_user_id', $edward->id)->pluck('id')->all()
        )
        ->and($mollyWork['advisor']['name'])->toBe('Molly')
        ->and($edwardWork['advisor']['name'])->toBe('Edward');
});

test('desk shows known caller context and unknown caller without inventing a customer', function (): void {
    [$user, $token] = deskAdvisor();
    $customer = Customer::query()->create(['first_name' => 'Alyssa', 'last_name' => 'Stasiak', 'phone' => '7195550123']);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Jeep',
        'model' => 'Cherokee',
    ]);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brakes',
        'opened_at' => now(),
    ]);
    $known = deskCall($user, $customer);
    $known->forceFill(['repair_order_id' => $ro->id])->save();
    $unknown = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CA'.uniqid(),
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550999',
        'to_number' => '+17195559999',
        'normalized_from' => '7195550999',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now(),
    ]);

    $this->withToken($token)
        ->getJson('/api/desk/calls/'.$known->id)
        ->assertOk()
        ->assertJsonPath('matched', true)
        ->assertJsonPath('customer.name', 'Alyssa Stasiak')
        ->assertJsonMissingPath('deep_link');

    $this->withToken($token)
        ->getJson('/api/desk/calls/'.$unknown->id)
        ->assertOk()
        ->assertJsonPath('matched', false)
        ->assertJsonPath('customer', null);
});

test('desk follow-up and handled use advisor_tasks and CallSession.worked_at', function (): void {
    [$user, $token] = deskAdvisor();
    $call = deskCall($user, null);

    $created = $this->withToken($token)
        ->postJson('/api/desk/tasks', [
            'title' => 'Call Alyssa tomorrow after 10',
            'call_session_id' => $call->id,
        ])
        ->assertOk()
        ->json();

    expect($created['task']['call_session_id'])->toBe($call->id);

    $this->withToken($token)
        ->postJson('/api/desk/tasks/'.$created['task']['id'].'/complete')
        ->assertOk()
        ->assertJsonPath('completed', true);

    $this->withToken($token)
        ->postJson('/api/desk/calls/'.$call->id.'/handled')
        ->assertOk()
        ->assertJsonPath('handled', true);

    expect($call->fresh()->worked_at)->not->toBeNull()
        ->and(AdvisorTask::query()->find($created['task']['id'])->completed_at)->not->toBeNull();
});

test('desk dragon uses staff hosted chat and stays useful when Dragon is down', function (): void {
    [$user, $token] = deskAdvisor();
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn('Waiting approval is live from ARK.', []),
    ];

    $this->withToken($token)
        ->postJson('/api/desk/dragon/chat', ['message' => 'What should I focus on?'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $fake->unavailable = true;
    $this->withToken($token)
        ->postJson('/api/desk/dragon/chat', ['message' => 'What should I focus on?'])
        ->assertStatus(503);

    $this->withToken($token)
        ->getJson('/api/desk/work')
        ->assertOk()
        ->assertJsonPath('health.ark', 'ok');
});

test('one advisor cannot complete the other advisor private task', function (): void {
    [$molly, $mollyToken] = deskAdvisor('Molly');
    [, $edwardToken] = deskAdvisor('Edward');
    $task = AdvisorTask::query()->create([
        'notes' => 'Molly only',
        'assigned_user_id' => $molly->id,
        'created_by_user_id' => $molly->id,
        'due_at' => now()->addHour(),
    ]);

    $this->withToken($edwardToken)
        ->postJson('/api/desk/tasks/'.$task->id.'/complete')
        ->assertForbidden();
});

test('desk work names the tenant shop and occupying a station is operator intent', function (): void {
    ShopSettings::current()->update(['shop_name' => 'Demo Auto Repair']);
    [$molly] = deskAdvisor('Molly');
    $front = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Front Counter',
        'location_label' => 'Front Counter',
        'is_active' => true,
    ]);
    $office = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Service Office',
        'location_label' => 'Service Office',
        'is_active' => true,
        'current_operator_user_id' => $molly->id,
    ]);

    Sanctum::actingAs($molly);
    $this->getJson('/api/desk/work')
        ->assertOk()
        ->assertJsonPath('shop.name', 'Demo Auto Repair')
        ->assertJsonPath('workstation.name', 'Service Office');

    $this->postJson('/api/desk/workstation', ['workstation_id' => $front->id])
        ->assertOk()
        ->assertJsonPath('workstation.id', $front->id)
        ->assertJsonPath('workstation.name', 'Front Counter');

    expect($office->fresh()->current_operator_user_id)->toBeNull()
        ->and($front->fresh()->current_operator_user_id)->toBe($molly->id);
});
