<?php

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyHealth;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Schema;

test('communications settings keep messaging transport not configured without credential fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'general',
            'telephony_inbound_number' => '+17195550100',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'general',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->telephony_inbound_number)->toBe('+17195550100');

    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->messagingConfigured())->toBeFalse()
        ->and($credentials->twilioConfigured())->toBeFalse()
        ->and(TelephonyHealth::forCurrentShop()->credentialsConfigured())->toBeFalse();

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'general',
        ]))
        ->assertOk()
        ->assertDontSee('Account SID', false)
        ->assertSee('require a messaging/voice transport implementation', false);
});

test('payments settings save square credentials encrypted in shop settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    config()->set('services.square.application_id', null);
    config()->set('services.square.access_token', null);
    config()->set('services.square.location_id', null);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.payments.update'), [
            'square_application_id' => 'sq0idp-test-app',
            'square_access_token' => 'sq-test-access-token',
            'square_location_id' => 'LOC-TEST',
            'square_webhook_signature_key' => 'wh-test-key',
            'square_environment' => 'sandbox',
            'square_enabled' => '1',
            'square_terminal_enabled' => '1',
            'square_keyed_enabled' => '1',
            'square_portal_pay_enabled' => '1',
            'square_email_pay_enabled' => '1',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']));

    $settings = ShopSettings::current()->fresh();

    expect($settings->square_application_id)->toBe('sq0idp-test-app')
        ->and($settings->square_access_token)->toBe('sq-test-access-token')
        ->and($settings->square_location_id)->toBe('LOC-TEST')
        ->and($settings->square_webhook_signature_key)->toBe('wh-test-key')
        ->and($settings->square_environment)->toBe('sandbox');

    expect(ShopIntegrationCredentials::forCurrentShop()->squareConfigured())->toBeTrue();
});

test('payments settings leave blank secrets unchanged', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->persistTrusted([
        'square_application_id' => 'sq0idp-existing',
        'square_access_token' => 'existing-token',
        'square_location_id' => 'LOC-EXISTING',
        'square_environment' => 'production',
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.payments.update'), [
            'square_application_id' => 'sq0idp-existing',
            'square_access_token' => '',
            'square_location_id' => 'LOC-EXISTING',
            'square_environment' => 'production',
            'square_enabled' => '0',
            'square_terminal_enabled' => '1',
            'square_keyed_enabled' => '1',
            'square_portal_pay_enabled' => '1',
            'square_email_pay_enabled' => '1',
        ])
        ->assertRedirect();

    expect(ShopSettings::current()->fresh()->square_access_token)->toBe('existing-token');
});

test('shop integration credentials fall back to env when database is empty', function () {
    ShopSettings::current()->persistTrusted([
        'square_application_id' => null,
        'square_access_token' => null,
        'square_location_id' => null,
        'square_webhook_signature_key' => null,
        'square_environment' => null,
    ]);

    config()->set('services.square.application_id', 'sq-env-app');
    config()->set('services.square.access_token', 'sq-env-token');
    config()->set('services.square.location_id', 'LOC-ENV');

    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->messagingConfigured())->toBeFalse()
        ->and($credentials->twilioCredentialSource())->toBe('none')
        ->and($credentials->squareCredentialSource())->toBe('env')
        ->and($credentials->partsTechCredentialSource())->toBe('none')
        ->and($credentials->partsTechCatalogConfigured())->toBeFalse();
});

test('email settings save reply-to and surface ARK Mail without Postmark credential fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->persistTrusted([
        'learn_training_gate_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.email.update'), [
            'postmark_reply_to' => 'service@example.com',
            'postmark_reply_to_name' => 'Example Shop',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->postmark_reply_to)->toBe('service@example.com')
        ->and($settings->postmark_reply_to_name)->toBe('Example Shop')
        ->and(Schema::hasColumn('shop_settings', 'postmark_token'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'email_provider'))->toBeFalse();

    $blade = view('operations.settings.partials.customer-email-settings', [
        'settings' => $settings,
    ])->render();

    expect($blade)->toContain('ARK Mail')
        ->and($blade)->toContain('Connect ARK Mail')
        ->and($blade)->not->toContain('name="postmark_token"')
        ->and($blade)->not->toContain('value="postmark"');
});

test('integration settings pages show credential fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit', ['section' => 'communications']))
        ->assertOk()
        ->assertDontSee('Account SID', false)
        ->assertDontSee('Auth token', false)
        ->assertSee('messaging/voice transport implementation', false);

    $this->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('Application ID')
        ->assertSee('Access token')
        ->assertSee('Webhook signature key');

    $this->get(route('operations.settings.shop.edit', [
        'section' => 'communications',
        'communications-tab' => 'email',
    ]))
        ->assertOk()
        ->assertSee('ARK Mail')
        ->assertSee('Reply-To');
});

test('shop integration credentials prefer database values over env fallback', function () {
    config()->set('services.square.application_id', 'sq-env-app');
    config()->set('services.square.access_token', 'sq-env-token');
    config()->set('services.square.location_id', 'LOC-ENV');
    config()->set('services.square.webhook_signature_key', 'wh-env');
    config()->set('services.square.environment', 'sandbox');

    ShopSettings::current()->persistTrusted([
        'square_application_id' => 'sq-db-app',
        'square_access_token' => 'sq-db-token',
        'square_location_id' => 'LOC-DB',
        'square_webhook_signature_key' => 'wh-db',
        'square_environment' => 'production',
    ]);

    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->messagingConfigured())->toBeFalse()
        ->and($credentials->twilioAccountSid())->toBeNull()
        ->and($credentials->squareEnvironment())->toBe('production')
        ->and($credentials->squareCredentialSource())->toBe('database');
});
