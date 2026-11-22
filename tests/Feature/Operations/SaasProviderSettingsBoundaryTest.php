<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Platform\Payments\ManagedPaymentsGate;
use App\Ark\Platform\SaasProviderConfigurationGate;
use App\Ark\Platform\Voice\ManagedVoiceGate;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    ManagedVoiceGate::resetMemo();
    ShopSettings::forgetCurrent();

    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

test('standalone settings hide SaaS provider nav and refuse direct communications URLs', function (): void {
    expect(SaasProviderConfigurationGate::platformConnected())->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertSee('Payment methods')
        ->assertDontSee('Square Payments')
        ->assertDontSee("@click=\"setActive('communications')\"", false)
        ->assertDontSee("@click=\"setActive('ark-cloud')\"", false)
        ->assertDontSee('Connect this Box')
        ->assertSee('Stations & Phones')
        ->assertSee('Parts catalogs');

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'communications']))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'ark-cloud']))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'general',
            'telephony_inbound_number' => '+15551234567',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.payments.update'), [
            'square_terminal_enabled' => '1',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']));

    $this->actingAs($this->admin)
        ->post(route('operations.settings.shop.ark-cloud.connect'))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));
});

test('standalone payments section keeps manual methods without card-capture form', function (): void {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('data-settings-manual-payment-methods', false)
        ->assertSee('Integrated card capture is not available on standalone Core')
        ->assertDontSee('data-settings-card-payments', false)
        ->assertDontSee('Save card payment options');

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.manual-payment-methods.update'), [
            'methods' => [
                ['key' => 'check', 'label' => 'Check'],
                ['key' => 'external_card', 'label' => 'External Card'],
                ['key' => 'eft', 'label' => 'EFT'],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']));
});

test('hosted platform connection restores communications settings and card capture controls', function (): void {
    enablePlatformConnection();
    ShopSettings::reloadCurrent();
    fakePlatformVoiceStatus('active', runtimeOwner: 'platform');
    config(['services.ark_platform.payments_capture' => true]);

    expect(SaasProviderConfigurationGate::allowsCommunicationsProviderSettings())->toBeTrue()
        ->and(ManagedPaymentsGate::platformCapture())->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertSee("@click=\"setActive('communications')\"", false)
        ->assertDontSee("@click=\"setActive('ark-cloud')\"", false);

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'communications']))
        ->assertOk()
        ->assertSee('Phone, SMS, and email');

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('data-settings-card-payments', false)
        ->assertSee('data-settings-manual-payment-methods', false)
        ->assertSee('Card capture is connected for this shop');
});

test('standalone stations page keeps workstations and refuses phone device routes', function (): void {
    $this->actingAs($this->admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Physical workstations stay in Core')
        ->assertSee('Phone device provisioning belongs to ARK Shop');

    $this->actingAs($this->admin)
        ->post(route('operations.shop.devices.store'), [
            'name' => 'Front desk phone',
            'mac_address' => '00:11:22:33:44:55',
            'provider' => 'shop_phone',
        ])
        ->assertRedirect(route('operations.shop.communications'));
});

test('box pairing routes refuse without mutating platform connection storage', function (): void {
    ShopSettings::current()->persistTrusted([
        'platform_status' => null,
        'platform_credential' => null,
    ]);

    $this->actingAs($this->admin)
        ->post(route('operations.cloud.connect'))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));

    $settings = ShopSettings::current()->fresh();
    expect($settings->platform_status)->toBeNull()
        ->and($settings->platform_credential)->toBeNull();
});
