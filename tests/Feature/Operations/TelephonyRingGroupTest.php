<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyRingGroup;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
    TelephonyEndpoint::query()->delete();

    ShopSettings::current()->update([
        'telephony_call_flow' => ShopSettings::defaultTelephonyCallFlow(),
        'learn_training_gate_enabled' => false,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-16 10:00:00', 'America/Denver'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('twiml includes multiple enabled endpoints with number and sip children', function () {
    $edward = User::factory()->create(['phone' => '7195551001']);
    $molly = User::factory()->create(['phone' => '7195551002']);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $edward->id,
        'enabled' => true,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Molly Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $molly->id,
        'enabled' => true,
        'position' => 1,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@lugsnplugs.sip.twilio.com',
        'enabled' => true,
        'position' => 2,
    ]);

    $response = $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAringgroup1',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ]);

    $response->assertOk()
        ->assertSee('<Number', false)
        ->assertSee('+17195551001</Number>', false)
        ->assertSee('+17195551002</Number>', false)
        ->assertSee('<Sip', false)
        ->assertSee('sip:101@lugsnplugs.sip.twilio.com</Sip>', false)
        ->assertSee('statusCallback="'.route('webhooks.communications.twilio.voice.status').'"', false)
        ->assertSee('statusCallbackEvent="answered in-progress"', false);
});

test('disabled endpoints are excluded from twiml', function () {
    $edward = User::factory()->create(['phone' => '7195551001']);
    $molly = User::factory()->create(['phone' => '7195551002']);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $edward->id,
        'enabled' => true,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Molly Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $molly->id,
        'enabled' => false,
        'position' => 1,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAringgroup2',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('+17195551001</Number>', false)
        ->assertDontSee('+17195551002</Number>', false);
});

test('empty endpoint list falls back to safe say message', function () {
    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAringgroup3',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Say voice="alice">', false)
        ->assertDontSee('<Dial', false);
});

test('migrated forward destination pattern rings through endpoint table', function () {
    TelephonyEndpoint::query()->create([
        'name' => 'Forward destination',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195558888',
        'enabled' => true,
        'position' => 0,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAmigrated01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('7195558888</Number>', false);
});

test('telephony settings can save ring delay seconds per endpoint', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $ben = User::factory()->create(['phone' => '7195551001']);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'ring',
            'endpoints' => [
                [
                    'name' => 'Ben SIP',
                    'type' => 'sip',
                    'destination' => 'sip:desk1@lugsnplugs.sip.twilio.com',
                    'ring_delay_seconds' => '0',
                    'enabled' => '1',
                ],
                [
                    'name' => 'Ben Cell',
                    'type' => 'cell',
                    'user_id' => (string) $ben->id,
                    'ring_delay_seconds' => '15',
                    'ring_schedule' => 'always',
                    'enabled' => '1',
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'ring',
        ]));

    expect(TelephonyEndpoint::query()->where('name', 'Ben SIP')->value('ring_delay_seconds'))->toBe(0)
        ->and(TelephonyEndpoint::query()->where('name', 'Ben Cell')->value('ring_delay_seconds'))->toBe(15);
});

test('telephony settings can save ring group endpoints', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $edward = User::factory()->create(['phone' => '7195551001']);
    $molly = User::factory()->create(['phone' => '7195551002']);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'ring',
            'endpoints' => [
                [
                    'name' => 'Edward Cell',
                    'type' => 'cell',
                    'user_id' => (string) $edward->id,
                    'ring_schedule' => 'always',
                    'enabled' => '1',
                ],
                [
                    'name' => 'Molly Cell',
                    'type' => 'cell',
                    'user_id' => (string) $molly->id,
                    'ring_schedule' => 'when_present',
                    'presence_timeout_minutes' => '45',
                    'enabled' => '1',
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'communications', 'communications-tab' => 'ring']));

    expect(TelephonyEndpoint::query()->count())->toBe(2)
        ->and(TelephonyEndpoint::query()->where('name', 'Edward Cell')->value('destination'))->toBe('7195551001')
        ->and(TelephonyEndpoint::query()->where('name', 'Molly Cell')->value('presence_timeout_minutes'))->toBe(45)
        ->and(app(TelephonyRingGroup::class)->enabledEndpoints())->toHaveCount(2);
});

test('sip destinations are normalized with sip prefix when saved', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'ring',
            'endpoints' => [
                [
                    'name' => 'Front Desk SIP',
                    'type' => 'sip',
                    'destination' => '101@lugsnplugs.sip.twilio.com',
                    'enabled' => '1',
                ],
            ],
        ])
        ->assertRedirect();

    expect(TelephonyEndpoint::query()->first()?->destination)
        ->toBe('sip:101@lugsnplugs.sip.twilio.com');
});

test('telephony settings require profile cell for enabled cell endpoint without phone', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $molly = User::factory()->create(['phone' => null]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'ring',
            'endpoints' => [
                [
                    'name' => 'Molly Cell',
                    'type' => 'cell',
                    'user_id' => (string) $molly->id,
                    'ring_schedule' => 'when_present',
                    'enabled' => '1',
                ],
            ],
        ])
        ->assertSessionHasErrors('endpoints.0.user_id');

    expect(TelephonyEndpoint::query()->count())->toBe(0);
});

test('telephony settings can save inbound and outbound recording toggles', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'recording',
            'telephony_call_flow' => [
                'record_inbound_calls' => '0',
                'record_outbound_calls' => '0',
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'recording',
        ]));

    $flow = TelephonyCallFlowSettings::fromShopSettings();

    expect($flow->recordInboundCalls())->toBeFalse()
        ->and($flow->recordOutboundCalls())->toBeFalse();
});

test('cell endpoint resolves phone from linked staff profile', function () {
    $edward = User::factory()->create(['phone' => '7195551001']);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $edward->id,
        'enabled' => true,
        'position' => 0,
    ]);

    expect($cell->dialDestination())->toBe('7195551001')
        ->and($cell->toTwimlChild())->toBe('<Number answerOnBridge="true">+17195551001</Number>');
});

test('ring group builder renders cell and sip twiml children', function () {
    $edward = User::factory()->create(['phone' => '7195551001']);

    $cell = TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '719-555-1001',
        'user_id' => $edward->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $sip = TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@example.com',
        'enabled' => true,
        'position' => 1,
    ]);

    expect($cell->toTwimlChild())->toBe('<Number answerOnBridge="true">+17195551001</Number>')
        ->and($cell->toTwimlChild(route('webhooks.communications.twilio.voice.status')))
        ->toContain('statusCallbackEvent="answered in-progress"')
        ->and($sip->toTwimlChild())->toBe('<Sip>sip:101@example.com</Sip>');
});

test('desk only inbound ring is ignored when asterisk ingress is disabled', function () {
    ShopSettings::current()->update([
        'asterisk_voice' => [
            'ingress_enabled' => false,
            'desk_only_inbound_ring' => true,
        ],
    ]);

    $edward = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $edward->id,
        'enabled' => true,
        'position' => 0,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAdeskonlyoff01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('+17195551001</Number>', false);
});

test('desk only inbound ring setting excludes cell and mobile from twiml', function () {
    ShopSettings::current()->update([
        'asterisk_voice' => [
            'ingress_enabled' => true,
            'desk_only_inbound_ring' => true,
        ],
    ]);

    $edward = User::factory()->create(['phone' => '7195551001']);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $edward->id,
        'enabled' => true,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@lugsnplugs.sip.twilio.com',
        'enabled' => true,
        'position' => 1,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAdeskonly01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Sip', false)
        ->assertDontSee('<Number', false)
        ->assertDontSee('<Client', false);
});

test('inbound ring skips cell endpoint when caller is the same number', function () {
    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195551001',
        'enabled' => true,
        'position' => 0,
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@lugsnplugs.sip.twilio.com',
        'enabled' => true,
        'position' => 1,
    ]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnoselfcell01',
        'From' => '+17195551001',
        'To' => '+17195559999',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('<Sip', false)
        ->assertDontSee('+17195551001</Number>', false);
});
