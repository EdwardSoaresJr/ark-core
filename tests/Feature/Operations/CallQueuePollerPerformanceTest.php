<?php

use App\Ark\Operations\Communications\CommunicationsQueueResolver;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('call queue poller does not write during a get and omits unused html', function () {
    $advisor = User::factory()->create([
        'last_seen_at' => now(),
    ])->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CApoller001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551001',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($advisor);

    $measured = measureQueries(fn () => $this->getJson(route('operations.telephony.call-queue')));
    $response = $measured['result'];

    $response
        ->assertOk()
        ->assertJsonPath('summary.has_live_calls', false)
        ->assertJsonPath('recent_activity', []);

    expect($response->json())->not->toHaveKey('html')
        ->and(callSessionMutationQueries($measured['queries']))->toBe([])
        ->and(CallSession::query()->where('provider_call_sid', 'CApoller001')->value('status'))
        ->toBe(CallSessionStatus::Ringing);

    $this->artisan('comms:reconcile-stale-call-sessions')->assertSuccessful();

    expect(CallSession::query()->where('provider_call_sid', 'CApoller001')->value('status'))
        ->toBe(CallSessionStatus::Missed);
});

test('comms interrupt poller does not write during a get', function () {
    $advisor = User::factory()->create([
        'last_seen_at' => now(),
    ])->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAintpoll001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($advisor);

    $measured = measureQueries(fn () => $this->getJson(route('operations.comms.interrupts')));

    $measured['result']
        ->assertOk()
        ->assertJsonPath('call', null);

    expect(callSessionMutationQueries($measured['queries']))->toBe([])
        ->and(CallSession::query()->where('provider_call_sid', 'CAintpoll001')->value('status'))
        ->toBe(CallSessionStatus::Ringing);
});

test('communications queue api get does not write call sessions during a poll', function () {
    $advisor = User::factory()->create([
        'last_seen_at' => now(),
    ])->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAqueueapi001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551888',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551888',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($advisor);

    $measured = measureQueries(fn () => $this->getJson(route('operations.communications.queue.api')));

    $measured['result']->assertOk();

    expect(callSessionMutationQueries($measured['queries']))->toBe([])
        ->and(CallSession::query()->where('provider_call_sid', 'CAqueueapi001')->value('status'))
        ->toBe(CallSessionStatus::Ringing);
});

test('call queue poller payload is smaller than the unused full html response', function () {
    $advisor = User::factory()->create([
        'last_seen_at' => now(),
    ])->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAsize001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551002',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551002',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinutes(6),
    ]);

    $this->actingAs($advisor);

    $poller = measureQueries(fn () => $this->getJson(route('operations.telephony.call-queue')));
    $response = $poller['result']->assertOk();

    $full = measureQueries(fn () => app(CommunicationsQueueResolver::class)->resolve($advisor));
    $html = view('operations.communications.partials.call-queue-items-list', [
        'items' => $full['result']['items'] ?? [],
    ])->render();
    $legacyBytes = strlen(json_encode([
        ...$full['result'],
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE));
    $lightBytes = strlen($response->getContent());

    expect($response->json('recent_activity'))->toBe([])
        ->and($lightBytes)->toBeLessThan($legacyBytes)
        ->and($legacyBytes - $lightBytes)->toBeGreaterThan(strlen($html) / 2);
});

test('call queue and interrupt pollers are initialized once in source', function () {
    $callQueue = file_get_contents(resource_path('js/ark-call-queue.js'));
    $interrupt = file_get_contents(resource_path('js/ark-comms-interrupt.js'));
    $callQueueBlade = file_get_contents(resource_path('views/components/operations/call-queue.blade.php'));
    $interruptBlade = file_get_contents(resource_path('views/components/operations/comms-interrupt-panel.blade.php'));

    expect($callQueueBlade)->not->toContain('x-init="init()"')
        ->and($interruptBlade)->not->toContain('x-init="init()"')
        ->and($callQueue)->toContain('if (this.pollStarted)')
        ->and($callQueue)->toContain('destroy()')
        ->and($callQueue)->toContain('refreshQueued')
        ->and($callQueue)->toContain('window.setInterval(() => this.refresh(), 5000)')
        ->and($callQueue)->not->toContain('payload.html')
        ->and($interrupt)->toContain('if (this.pollStarted)')
        ->and($interrupt)->toContain('destroy()')
        ->and($interrupt)->toContain('pollQueued');
});
