<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\Jobs\ExpandStaggeredRingJob;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyRingSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);

    ShopSettings::current()->update([
        'telephony_call_flow' => ShopSettings::defaultTelephonyCallFlow(),
        'telephony_inbound_number' => '+17195550100',
    ]);

    TelephonyEndpoint::query()->delete();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('parallel ring twiml is unchanged when all endpoint ring delays are zero', function () {
    $ben = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 0,
        'enabled' => true,
        'position' => 1,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAparallel01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Number', false)
        ->assertSee('answerOnBridge="true"', false)
        ->assertSee('ringTone="us"', false)
        ->assertSee('<Sip', false)
        ->assertDontSee('<Conference', false);
});

test('staggered ring uses conference wait twiml while immediate endpoints ring via outbound jobs', function () {
    $ben = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 15,
        'enabled' => true,
        'position' => 1,
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstagger01',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195550100',
        'normalized_from' => '7195551234',
        'normalized_to' => '7195550100',
        'status' => 'ringing',
        'started_at' => now(),
    ]);

    $xml = \App\Ark\Operations\Telephony\TelephonyIncomingCallFlow::forCurrentShop()->buildResponse('CAstagger01');

    expect($xml)
        ->toContain('<Conference')
        ->toContain('ringTone="us"')
        ->toContain('callerId="+17195551234"')
        ->not->toContain('<Pause')
        ->not->toContain('<Sip')
        ->not->toContain('+17195551001</Number>');
});

test('staggered ring dispatcher queues immediate outbound jobs and delayed expand jobs', function () {
    Queue::fake();

    config()->set('services.twilio.account_sid', 'ACtest');
    config()->set('services.twilio.auth_token', 'test-token');

    $ben = User::factory()->create(['phone' => '7195551001']);

    $sip = TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 15,
        'enabled' => true,
        'position' => 1,
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstagger01',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195550100',
        'normalized_from' => '7195551234',
        'normalized_to' => '7195550100',
        'status' => 'ringing',
        'started_at' => now(),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyStaggeredRingDispatcher::class)
        ->dispatchForParentCall('CAstagger01');

    Queue::assertPushed(\App\Ark\Operations\Telephony\Jobs\RingTelephonyEndpointJob::class, 1);
    Queue::assertPushed(\App\Ark\Operations\Telephony\Jobs\RingTelephonyEndpointJob::class, fn ($job): bool => $job->parentCallSid === 'CAstagger01'
        && $job->endpointId === $sip->id);
    Queue::assertPushed(ExpandStaggeredRingJob::class, 1);
    Queue::assertPushed(ExpandStaggeredRingJob::class, fn (ExpandStaggeredRingJob $job): bool => $job->parentCallSid === 'CAstagger01'
        && $job->maxDelaySeconds === 15);
    Queue::assertPushed(\App\Ark\Operations\Telephony\Jobs\CompleteUnansweredInboundCallJob::class, 1);
});

test('staggered expand job dials only the new delay tier without redirecting parent call', function () {
    Queue::fake();

    config()->set('services.twilio.account_sid', 'ACtest');
    config()->set('services.twilio.auth_token', 'test-token');

    $ben = User::factory()->create(['phone' => '7195551001']);

    $sip = TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 15,
        'enabled' => true,
        'position' => 1,
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initialize(
        'CAstagger02',
        'ark-ring-CAstagger02',
        '+17195550100',
        [],
        '7195551234',
    );
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->markExpanded('CAstagger02', 0);

    app(\App\Ark\Operations\Telephony\TelephonyStaggeredRingExpander::class)
        ->expand('CAstagger02', 15);

    Queue::assertPushed(\App\Ark\Operations\Telephony\Jobs\RingTelephonyEndpointJob::class, 1);
    Queue::assertPushed(\App\Ark\Operations\Telephony\Jobs\RingTelephonyEndpointJob::class, fn ($job): bool => $job->parentCallSid === 'CAstagger02'
        && $job->endpointId === $cell->id);
    Queue::assertNotPushed(\App\Ark\Operations\Telephony\Jobs\RingTelephonyEndpointJob::class, fn ($job): bool => $job->endpointId === $sip->id);
});

test('ring leg answered status assigns owner and cancels other outbound legs', function () {
    config()->set('services.twilio.account_sid', 'ACtest');
    config()->set('services.twilio.auth_token', 'test-token');

    $ben = User::factory()->create(['phone' => '7195551001']);

    $sip = TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 1,
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstagger03',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195550100',
        'normalized_from' => '7195551234',
        'normalized_to' => '7195550100',
        'status' => 'ringing',
        'started_at' => now(),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initialize(
        'CAstagger03',
        'ark-ring-CAstagger03',
        '+17195550100',
        [
            $sip->id => 'CAoutSip01',
            $cell->id => 'CAoutCell01',
        ],
    );

    Http::fake([
        'https://api.twilio.com/*' => Http::response([], 200),
    ]);

    $request = \Illuminate\Http\Request::create(
        route('webhooks.communications.twilio.voice.ring-status', [
            'parentCallSid' => 'CAstagger03',
            'endpointId' => $sip->id,
        ]),
        'POST',
        [
            'CallSid' => 'CAoutSip01',
            'CallStatus' => 'answered',
            'Called' => 'sip:desk1@example.sip.twilio.com',
        ],
    );

    app(\App\Ark\Operations\Telephony\TelephonyRingLegStatusHandler::class)
        ->handleRingLeg($request, 'CAstagger03', $sip->id);

    $session = CallSession::query()->where('provider_call_sid', 'CAstagger03')->first();

    expect($session?->owned_by_user_id)->toBe($ben->id);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Calls/CAoutCell01.json')
            && ($request['Status'] ?? null) === 'completed';
    });
});

test('answered ring leg completed terminalizes parent call session', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Ark\Operations\Communications\Events\CommsInterruptReceived::class]);
    config()->set('broadcasting.default', 'log');

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAhangup01',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'answered',
        'started_at' => now()->subMinute(),
        'answered_at' => now()->subMinute(),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initialize(
        'CAhangup01',
        'ark-ring-CAhangup01',
        '+17195550100',
    );
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->markAnswered('CAhangup01', 1);

    $this->post(route('webhooks.communications.twilio.voice.ring-status', [
        'parentCallSid' => 'CAhangup01',
        'endpointId' => 1,
    ]), [
        'CallSid' => 'CAoutLeg01',
        'CallStatus' => 'completed',
    ])->assertNoContent();

    $session->refresh();

    expect($session->status->value)->toBe('completed')
        ->and($session->ended_at)->not->toBeNull();

    \Illuminate\Support\Facades\Event::assertDispatched(\App\Ark\Operations\Communications\Events\CommsInterruptReceived::class, function ($event): bool {
        return ($event->payload['kind'] ?? '') === 'call'
            && ($event->payload['action'] ?? '') === 'clear';
    });
});

test('parallel ring includes cell whisper url and caller id passthrough', function () {
    $ben = User::factory()->create(['phone' => '7195551001']);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 0,
        'enabled' => true,
        'position' => 0,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAcallerid1',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('cell-whisper', false)
        ->assertSee((string) $cell->id, false);
});

test('cell whisper and accept connects staff who press 1', function () {
    ShopSettings::current()->update([
        'shop_name' => "Lug's N Plugs",
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'cell_whisper_prompt' => 'Call for Lug\'s N Plugs',
        ]),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initialize(
        'CAwhisper01',
        'ark-ring-CAwhisper01',
        '+17195550100',
        customerCallerId: '+17195551234',
    );

    $this->post(route('webhooks.communications.twilio.voice.cell-whisper', [
        'parentCallSid' => 'CAwhisper01',
        'endpointId' => 1,
    ]))->assertOk()
        ->assertSee('Press 1 to accept', false)
        ->assertSee("Call for Lug's N Plugs", false)
        ->assertDontSee('555-1234', false);

    $this->post(route('webhooks.communications.twilio.voice.cell-accept', [
        'parentCallSid' => 'CAwhisper01',
        'endpointId' => 1,
    ]), [
        'Digits' => '1',
    ])->assertOk()
        ->assertSee('<Conference', false);
});

test('parallel ring leg answer cancels competing cell leg call sid', function () {
    config()->set('services.twilio.account_sid', 'ACtest');
    config()->set('services.twilio.auth_token', 'test-token');

    $ben = User::factory()->create(['phone' => '7195551001']);

    $sip = TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'user_id' => $ben->id,
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 0,
        'enabled' => true,
        'position' => 1,
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initializeParallel('CAparallelLeg01');

    Http::fake([
        'https://api.twilio.com/*' => Http::response([], 200),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingLegStatusHandler::class)
        ->handleRingLeg(
            \Illuminate\Http\Request::create(
                route('webhooks.communications.twilio.voice.ring-status', [
                    'parentCallSid' => 'CAparallelLeg01',
                    'endpointId' => $cell->id,
                ]),
                'POST',
                ['CallSid' => 'CAchildCell01', 'CallStatus' => 'ringing'],
            ),
            'CAparallelLeg01',
            $cell->id,
        );

    app(\App\Ark\Operations\Telephony\TelephonyRingLegStatusHandler::class)
        ->handleRingLeg(
            \Illuminate\Http\Request::create(
                route('webhooks.communications.twilio.voice.ring-status', [
                    'parentCallSid' => 'CAparallelLeg01',
                    'endpointId' => $sip->id,
                ]),
                'POST',
                ['CallSid' => 'CAchildSip01', 'CallStatus' => 'answered'],
            ),
            'CAparallelLeg01',
            $sip->id,
        );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Calls/CAchildCell01.json')
            && ($request['Status'] ?? null) === 'completed';
    });
});

test('cell leg pickup marks screening without cancelling competing sip leg', function () {
    config()->set('services.twilio.account_sid', 'ACtest');
    config()->set('services.twilio.auth_token', 'test-token');

    $ben = User::factory()->create(['phone' => '7195551001']);

    $sip = TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 1,
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initializeParallel('CAcellScreen01');

    Http::fake([
        'https://api.twilio.com/*' => Http::response([], 200),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingLegStatusHandler::class)
        ->handleRingLeg(
            \Illuminate\Http\Request::create(
                route('webhooks.communications.twilio.voice.ring-status', [
                    'parentCallSid' => 'CAcellScreen01',
                    'endpointId' => $cell->id,
                ]),
                'POST',
                ['CallSid' => 'CAoutCell01', 'CallStatus' => 'answered'],
            ),
            'CAcellScreen01',
            $cell->id,
        );

    $state = app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->get('CAcellScreen01');

    expect($state['cell_screening'] ?? false)->toBeTrue()
        ->and($state['answered'] ?? true)->toBeFalse()
        ->and($state['cell_screening_endpoint_id'] ?? null)->toBe($cell->id);

    Http::assertNothingSent();
});

test('cell whisper still plays when same endpoint is screening', function () {
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initializeParallel('CAwhisperRace01');
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->markCellScreening('CAwhisperRace01', 7);

    config()->set('services.twilio.auth_token', null);

    $this->post(route('webhooks.communications.twilio.voice.cell-whisper', [
        'parentCallSid' => 'CAwhisperRace01',
        'endpointId' => 7,
    ]))->assertOk()
        ->assertSee('<Gather', false)
        ->assertSee('Press 1 to accept', false);
});

test('cell whisper hangs up when a different endpoint already answered', function () {
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initializeParallel('CAwhisperOther01');
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->markAnswered('CAwhisperOther01', 3);

    config()->set('services.twilio.auth_token', null);

    $this->post(route('webhooks.communications.twilio.voice.cell-whisper', [
        'parentCallSid' => 'CAwhisperOther01',
        'endpointId' => 7,
    ]))->assertOk()
        ->assertSee('<Hangup/>', false);
});

test('cell accept press 1 marks answered and cancels competing legs', function () {
    config()->set('services.twilio.account_sid', 'ACtest');
    config()->set('services.twilio.auth_token', 'test-token');

    $ben = User::factory()->create(['phone' => '7195551001']);

    $sip = TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 1,
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initialize(
        'CApress101',
        'ark-ring-CApress101',
        '+17195550100',
        [
            $sip->id => 'CAoutSip01',
            $cell->id => 'CAoutCell01',
        ],
    );
    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->markCellScreening('CApress101', $cell->id);

    Http::fake([
        'https://api.twilio.com/*' => Http::response([], 200),
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyCellWhisperFlow::class)
        ->acceptResponse('CApress101', $cell->id, '1');

    $state = app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->get('CApress101');

    expect($state['answered'] ?? false)->toBeTrue()
        ->and($state['cell_screening'] ?? true)->toBeFalse();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Calls/CAoutSip01.json')
            && ($request['Status'] ?? null) === 'completed';
    });
});

test('ring leg in progress status cancels competing outbound legs', function () {
    config()->set('services.twilio.account_sid', 'ACtest');
    config()->set('services.twilio.auth_token', 'test-token');

    $ben = User::factory()->create(['phone' => '7195551001']);

    $sip = TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 1,
    ]);

    app(\App\Ark\Operations\Telephony\TelephonyRingState::class)->initialize(
        'CAinprog01',
        'ark-ring-CAinprog01',
        '+17195550100',
        [
            $sip->id => 'CAoutSip01',
            $cell->id => 'CAoutCell01',
        ],
    );

    Http::fake([
        'https://api.twilio.com/*' => Http::response([], 200),
    ]);

    $request = \Illuminate\Http\Request::create(
        route('webhooks.communications.twilio.voice.ring-status', [
            'parentCallSid' => 'CAinprog01',
            'endpointId' => $sip->id,
        ]),
        'POST',
        [
            'CallSid' => 'CAoutSip01',
            'CallStatus' => 'in-progress',
        ],
    );

    app(\App\Ark\Operations\Telephony\TelephonyRingLegStatusHandler::class)
        ->handleRingLeg($request, 'CAinprog01', $sip->id);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Calls/CAoutCell01.json')
            && ($request['Status'] ?? null) === 'completed';
    });
});

test('staggered expand webhook dials cumulative endpoints with customer caller id', function () {
    $ben = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 15,
        'enabled' => true,
        'position' => 1,
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstaggerHint01',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195550100',
        'normalized_from' => '7195551234',
        'normalized_to' => '7195550100',
        'status' => 'ringing',
        'started_at' => now(),
    ]);

    $this->post(route('webhooks.communications.twilio.voice.staggered-expand', [
        'parentCallSid' => 'CAstaggerHint01',
        'maxDelay' => 15,
    ]))->assertOk()
        ->assertSee('<Sip', false)
        ->assertSee('<Number', false)
        ->assertSee('callerId="+17195551234"', false)
        ->assertSee('+17195551001</Number>', false);
});

test('single cell ring group disables machine detection', function () {
    $ben = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 0,
        'enabled' => true,
        'position' => 0,
    ]);

    $xml = app(\App\Ark\Operations\Telephony\TelephonyRingGroup::class)
        ->buildDialChildrenXml(parentCallSid: 'CAsingleCell01');

    expect($xml)
        ->toContain('<Number')
        ->not->toContain('machineDetection="Enable"');
});

test('parallel cell ring disables machine detection when multiple endpoints ring', function () {
    $ben = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 0,
        'enabled' => true,
        'position' => 1,
    ]);

    $xml = \App\Ark\Operations\Telephony\TelephonyIncomingCallFlow::forCurrentShop()->buildResponse('CAparallelAmd01');

    expect($xml)
        ->toContain('<Number')
        ->not->toContain('machineDetection="Enable"');
});

test('staggered ring with no immediate endpoints waits in conference until expand', function () {
    $ben = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 15,
        'enabled' => true,
        'position' => 0,
    ]);

    $xml = \App\Ark\Operations\Telephony\TelephonyIncomingCallFlow::forCurrentShop()->buildResponse('CAholdRing01');

    expect($xml)
        ->toContain('<Conference')
        ->toContain('ringTone="us"')
        ->not->toContain('<Play loop="0">')
        ->not->toContain('<Pause');
});

test('custom caller ring tone url is used on dial and play hold', function () {
    $flow = ShopSettings::defaultTelephonyCallFlow();
    $flow['caller_ring_tone'] = 'https://cdn.example.com/shop-special.mp3';
    ShopSettings::current()->update(['telephony_call_flow' => $flow]);

    $ben = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 0,
        'enabled' => true,
        'position' => 0,
    ]);

    $xml = \App\Ark\Operations\Telephony\TelephonyIncomingCallFlow::forCurrentShop()->buildResponse('CAcustomTone01');

    expect($xml)
        ->toContain('ringTone="https://cdn.example.com/shop-special.mp3"')
        ->toContain('answerOnBridge="true"');
});

test('staggered expand twiml includes caller ring tone on dial', function () {
    $ben = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:desk1@example.sip.twilio.com',
        'enabled' => true,
        'ring_delay_seconds' => 0,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Ben Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $ben->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'ring_delay_seconds' => 15,
        'enabled' => true,
        'position' => 1,
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAexpandTone01',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195550100',
        'normalized_from' => '7195551234',
        'normalized_to' => '7195550100',
        'status' => 'ringing',
        'started_at' => now(),
    ]);

    $xml = \App\Ark\Operations\Telephony\TelephonyIncomingCallFlow::forCurrentShop()
        ->buildStaggeredExpandResponse('CAexpandTone01', 15);

    expect($xml)->toContain('ringTone="us"');
});

test('conference join webhook returns conference twiml', function () {
    config()->set('services.twilio.auth_token', null);

    $this->post(route('webhooks.communications.twilio.voice.conference-join', [
        'conference' => 'ark-ring-CAjoin01',
        'parentCallSid' => 'CAjoin01',
        'endpointId' => 1,
    ]))->assertOk()
        ->assertSee('<Conference', false)
        ->assertSee('startConferenceOnEnter="true"', false)
        ->assertSee('ark-ring-CAjoin01', false);
});
