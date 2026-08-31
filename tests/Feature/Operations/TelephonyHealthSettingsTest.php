<?php

use App\Ark\Mobile\Push\MobilePushSettings;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyForwardNumber;
use App\Ark\Operations\Telephony\TelephonyHealth;
use App\Ark\Operations\Telephony\TelephonyShopSettings;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('settings communications telephony page surfaces operational health on general tab', function () {
    $this->seed(ArkAuthorizationSeeder::class);

            config()->set('broadcasting.default', 'reverb');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
        'telephony_call_flow' => array_merge(
            ShopSettings::current()->telephony_call_flow ?? ShopSettings::defaultTelephonyCallFlow(),
            ['comms_attention_gate_enabled' => false],
        ),
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195550999',
        'enabled' => true,
        'position' => 0,
    ]);

    cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now()->subMinutes(5), now()->addHour());

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CA-health-test',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195550100',
        'normalized_from' => '7195551234',
        'normalized_to' => '7195550100',
        'status' => 'ringing',
        'customer_id' => Customer::query()->create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone' => '7195551234',
        ])->id,
        'started_at' => now()->subMinutes(5),
    ]);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'general',
        ]))
        ->assertOk()
        ->assertSee('Phone')
        ->assertSee('Voice transport not configured')
                ->assertSee('Voice webhook')
        ->assertSee('SMS / MMS webhook')
        ->assertSee('Healthy')
        ->assertSee('John Smith')
        ->assertSee('Voice inbound')
        ->assertSee('SMS / MMS')
        ->assertSee('Copy')
        ->assertSee('Last voice webhook')
        ->assertSee('Last SMS / MMS webhook')
        ->assertSee('Test incoming call')
        ->assertSee('Account SID')
        ->assertDontSee('Voice API Key')
        ->assertDontSee('Programmable Voice')
        ->assertDontSee('Primary telephony provider')
        ->assertDontSee('Voice TwiML App SID');
});

test('settings communications email tab hides twilio health dashboard', function () {
    $this->seed(ArkAuthorizationSeeder::class);

        
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', [
        'section' => 'communications',
        'communications-tab' => 'email',
    ]))
        ->assertOk()
        ->assertSee('ARK Mail')
        ->assertSee('Server token')
        ->assertDontSee('Webhook URLs (paste into Twilio Console)')
        ->assertDontSee('Last incoming call')
        ->assertDontSee('Test incoming call');
});

test('settings communications ring tab shows configuration without full health dashboard', function () {
    $this->seed(ArkAuthorizationSeeder::class);

        
    TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195550999',
        'enabled' => true,
        'position' => 0,
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', [
        'section' => 'communications',
        'communications-tab' => 'ring',
    ]))
        ->assertOk()
        ->assertSee('Call routing')
        ->assertSee('Advisor Cell')
        ->assertSee('Presence')
        ->assertDontSee('SIP desk phone setup (Twilio)')
        ->assertDontSee('Programmable Voice')
        ->assertDontSee('Webhook URLs (paste into Twilio Console)')
        ->assertDontSee('Last incoming call');
});

test('settings communications hours tab does not show presence window', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', [
        'section' => 'communications',
        'communications-tab' => 'hours',
    ]))
        ->assertOk()
        ->assertSee('Call hours')
        ->assertDontSee('Presence');
});

test('saving communications recording tab does not wipe holiday closures or ring endpoints', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'closed_dates' => ['2026-12-25'],
        ]),
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Front Desk SIP',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@shop.sip.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'recording',
            'telephony_call_flow' => [
                'record_inbound_calls' => '0',
                'recording_disclaimer' => 'Calls may be recorded.',
            ],
        ])
        ->assertRedirect();

    $flow = TelephonyCallFlowSettings::fromShopSettings();

    expect($flow->recordInboundCalls())->toBeFalse()
        ->and($flow->toArray()['closed_dates'])->toBe(['2026-12-25'])
        ->and(TelephonyEndpoint::query()->count())->toBe(1);
});

test('ring tab can save shop promo caller ringback url', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $promoUrl = 'https://cdn.example.com/audio/summer-special.mp3';

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'ring',
            'telephony_call_flow' => [
                'caller_ring_audio_mode' => 'promo',
                'caller_ring_promo_url' => $promoUrl,
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'ring',
        ]));

    expect(TelephonyCallFlowSettings::fromShopSettings()->callerRingTone())->toBe($promoUrl);
});

test('saving hours tab preserves caller ringback setting', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'caller_ring_tone' => 'https://cdn.example.com/promo.mp3',
        ]),
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'hours',
            'telephony_call_flow' => [
                'timezone' => 'America/Denver',
            ],
        ])
        ->assertRedirect();

    expect(TelephonyCallFlowSettings::fromShopSettings()->callerRingTone())
        ->toBe('https://cdn.example.com/promo.mp3');
});

test('telephony forward number resolves from ring endpoint', function () {
    TelephonyEndpoint::query()->delete();

    TelephonyEndpoint::query()->create([
        'name' => 'Edward Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195550999',
        'enabled' => true,
        'position' => 0,
    ]);

    expect(TelephonyForwardNumber::resolve())->toBe('7195550999')
        ->and(TelephonyForwardNumber::sourceLabel())->toBe('Ring group');
});

test('settings managers can test incoming call from telephony page route', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('broadcasting.default', 'null');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->postJson(route('operations.settings.telephony.test-incoming-call'), [
            'phone' => '7195551234',
        ])
        ->assertOk()
        ->assertJsonPath('matched', false);

    expect(CallSession::query()->count())->toBe(1);
});

test('master admin infrastructure save redirects to runtime health', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $admin->forceFill(['is_master_admin' => true])->save();

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'infrastructure',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'runtime-health',
        ]));
});

test('non master admin cannot save communications infrastructure settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $admin->forceFill(['is_master_admin' => false])->save();

    $this->actingAs($admin->fresh())
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'infrastructure',
        ])
        ->assertRedirect(route('operations.index'));
});

test('settings managers can save mobile push shop toggle', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'mobile',
            'mobile_push' => [
                'enabled' => '1',
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'mobile',
        ]));

    $settings = MobilePushSettings::fromShopSettings(ShopSettings::current());

    expect($settings->enabled)->toBeTrue();
});
