<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Platform\Payments\ManagedPaymentsGate;
use App\Ark\Platform\SaasProviderConfigurationGate;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    // Clears ManagedVoiceGate memo without importing App\Ark\Platform\Voice (communications CODE_DIRS).
    fakePlatformStatusUnavailable();
    ShopSettings::forgetCurrent();
    clearHostedTenantCatalog($this->hostedCatalog ?? null);
    $this->hostedCatalog = null;

    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

afterEach(function (): void {
    clearHostedTenantCatalog($this->hostedCatalog ?? null);
    $this->hostedCatalog = null;
});

test('standalone settings hide SaaS provider nav and refuse direct communications URLs', function (): void {
    expect(SaasProviderConfigurationGate::isHostedRuntime())->toBeFalse();

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

test('legacy Platform connection rows alone do not unlock SaaS provider settings', function (): void {
    enablePlatformConnection();
    ShopSettings::reloadCurrent();

    expect(SaasProviderConfigurationGate::isHostedRuntime())->toBeFalse()
        ->and(SaasProviderConfigurationGate::allowsCommunicationsProviderSettings())->toBeFalse()
        ->and(SaasProviderConfigurationGate::allowsCardCaptureSettings())->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'communications']))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));
});

test('hosted tenant catalog restores communications settings shell without Box pairing', function (): void {
    $this->hostedCatalog = enableHostedTenantCatalog();
    ShopSettings::reloadCurrent();
    fakePlatformVoiceStatus('active', runtimeOwner: 'platform');

    expect(SaasProviderConfigurationGate::allowsCommunicationsProviderSettings())->toBeTrue()
        // Stock Core ManagedPaymentsGate still requires legacy pairing; composed SaaS replaces it.
        ->and(SaasProviderConfigurationGate::allowsCardCaptureSettings())->toBeFalse()
        ->and(ManagedPaymentsGate::platformCapture())->toBeFalse();

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
        ->assertDontSee('data-settings-card-payments', false)
        ->assertSee('data-settings-manual-payment-methods', false);
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
