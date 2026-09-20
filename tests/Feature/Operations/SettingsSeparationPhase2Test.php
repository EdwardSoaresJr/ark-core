<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Platform\Voice\ManagedVoiceGate;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    ManagedVoiceGate::resetMemo();

    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

test('platform connection without voice service keeps core hours settings', function (): void {
    enablePlatformConnection();
    fakePlatformVoiceStatus('not_enabled');

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'hours',
        ]))
        ->assertOk()
        ->assertSee('Call hours')
        ->assertDontSee('This phone control moved to ARK Cloud', false)
        ->assertDontSee('Phone settings are managed in ARK Cloud', false);
});

test('platform connection without voice service still saves call hours', function (): void {
    enablePlatformConnection();
    fakePlatformVoiceStatus('not_enabled');

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'hours',
            'telephony_call_flow' => [
                'weekly_hours' => [
                    'monday' => ['enabled' => '1', 'open' => '09:00', 'close' => '20:00'],
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'hours',
        ]))
        ->assertSessionHas('status', 'Telephony settings saved.');

    expect(TelephonyCallFlowSettings::fromShopSettings(ShopSettings::current())->weeklyHours()['monday']['close'] ?? null)
        ->toBe('20:00');
});

test('unavailable voice status keeps core phone settings', function (): void {
    enablePlatformConnection();
    fakePlatformStatusUnavailable();

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'ring',
        ]))
        ->assertOk()
        ->assertSee('Call routing')
        ->assertDontSee('This phone control moved to ARK Cloud', false);
});

test('active platform voice hides hours recording and call routing editors', function (): void {
    enablePlatformConnection();
    fakePlatformVoiceStatus('active', 'Active');

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'hours',
        ]))
        ->assertOk()
        ->assertSee('Phone settings are managed in ARK Cloud', false)
        ->assertSee('This phone control moved to ARK Cloud', false)
        ->assertDontSee('name="telephony_call_flow[weekly_hours][monday][open]"', false);
});

test('active platform voice refuses hours save', function (): void {
    enablePlatformConnection();
    fakePlatformVoiceStatus('active', 'Active');

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'hours',
            'telephony_call_flow' => [
                'weekly_hours' => [
                    'monday' => ['enabled' => '1', 'open' => '09:00', 'close' => '20:00'],
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'hours',
        ]))
        ->assertSessionHas('status', 'Phone settings are managed in ARK Cloud.');
});

test('platform connection without voice still saves the shop business number', function (): void {
    enablePlatformConnection();
    fakePlatformVoiceStatus('not_enabled');

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'general',
            'telephony_inbound_number' => '+17195550100',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'general',
        ]));

    expect(ShopSettings::current()->fresh()->telephony_inbound_number)->toBe('+17195550100');
});
