<?php

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyHealth;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Schema;

test('communications settings save twilio credentials encrypted in shop settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    config()->set('services.twilio.account_sid', null);
    config()->set('services.twilio.auth_token', null);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'general',
            'telephony_inbound_number' => '+17195550100',
            'twilio_account_sid' => 'AC-settings-test',
            'twilio_auth_token' => 'secret-twilio-token',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'general',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->twilio_account_sid)->toBe('AC-settings-test')
        ->and($settings->twilio_auth_token)->toBe('secret-twilio-token');

    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->twilioConfigured())->toBeTrue()
        ->and(TelephonyHealth::forCurrentShop()->credentialsConfigured())->toBeTrue();
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
        'twilio_account_sid' => null,
        'twilio_auth_token' => null,
        'square_application_id' => null,
        'square_access_token' => null,
        'square_location_id' => null,
        'square_webhook_signature_key' => null,
        'square_environment' => null,
        'partstech_username' => null,
        'partstech_api_key' => null,
        'partstech_password' => null,
    ]);

    config()->set('services.twilio.account_sid', 'AC-env-only');
    config()->set('services.twilio.auth_token', 'token-env-only');
    config()->set('services.square.application_id', 'sq-env-app');
    config()->set('services.square.access_token', 'sq-env-token');
    config()->set('services.square.location_id', 'LOC-ENV');
    config()->set('services.partstech.username', 'parts-env');
    config()->set('services.partstech.password', 'parts-secret');

    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->twilioCredentialSource())->toBe('env')
        ->and($credentials->squareCredentialSource())->toBe('env')
        ->and($credentials->partsTechCredentialSource())->toBe('env');
});

test('partstech settings save credentials encrypted in shop settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    config()->set('services.partstech.username', null);
    config()->set('services.partstech.password', null);
    config()->set('services.partstech.api_key', null);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.partstech.update'), [
            'partstech_base_url' => 'https://partstech.test',
            'partstech_username' => 'ark-shop',
            'partstech_api_key' => 'api-key-secret',
            'partstech_password' => 'shop-password',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'partstech']));

    $settings = ShopSettings::current()->fresh();
    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($settings->partstech_username)->toBe('ark-shop')
        ->and($settings->partstech_api_key)->toBe('api-key-secret')
        ->and($settings->partstech_password)->toBe('shop-password')
        ->and($credentials->partsTechCatalogConfigured())->toBeTrue()
        ->and($credentials->partsTechQuoteImportConfigured())->toBeTrue();
});

test('email settings save provider choice and reply-to without exposing stored token', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->persistTrusted([
        'learn_training_gate_enabled' => false,
        'email_provider' => 'postmark',
        'postmark_token' => 'pm-already-stored-token',
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.email.update'), [
            'email_provider' => 'postmark',
            'postmark_reply_to' => 'service@example.com',
            'postmark_reply_to_name' => 'Example Shop',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->email_provider)->toBe('postmark')
        ->and($settings->postmark_reply_to)->toBe('service@example.com')
        ->and($settings->postmark_reply_to_name)->toBe('Example Shop')
        ->and(Schema::hasColumn('shop_settings', 'postmark_token'))->toBeTrue()
        ->and(Schema::hasColumn('shop_settings', 'email_provider'))->toBeTrue()
        ->and($settings->postmark_token)->toBe('pm-already-stored-token');

    // Token must never be rendered back after save (encrypted at rest; blank input on edit).
    $blade = view('operations.settings.partials.customer-email-settings', [
        'settings' => $settings,
    ])->render();

    expect($blade)->not->toContain('pm-already-stored-token')
        ->and($blade)->toContain('Postmark');
});

test('integration settings pages show credential fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit', ['section' => 'communications']))
        ->assertOk()
        ->assertSee('Twilio credentials')
        ->assertSee('Account SID')
        ->assertSee('Auth token');

    $this->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('Application ID')
        ->assertSee('Access token')
        ->assertSee('Webhook signature key');

    $this->get(route('operations.settings.shop.edit', ['section' => 'partstech']))
        ->assertOk()
        ->assertSee('PartsTech')
        ->assertSee('Shop username');

    $this->get(route('operations.settings.shop.edit', [
        'section' => 'communications',
        'communications-tab' => 'email',
    ]))
        ->assertOk()
        ->assertSee('ARK Mail')
        ->assertSee('Reply-To');
});

test('shop integration credentials prefer database values over env fallback', function () {
    config()->set('services.twilio.account_sid', 'AC-from-env');
    config()->set('services.twilio.auth_token', 'token-from-env');
    config()->set('services.square.application_id', 'sq-env-app');
    config()->set('services.square.access_token', 'sq-env-token');
    config()->set('services.square.location_id', 'LOC-ENV');
    config()->set('services.square.webhook_signature_key', 'wh-env');
    config()->set('services.square.environment', 'sandbox');

    ShopSettings::current()->persistTrusted([
        'twilio_account_sid' => 'AC-from-db',
        'twilio_auth_token' => 'token-from-db',
        'square_application_id' => 'sq-db-app',
        'square_access_token' => 'sq-db-token',
        'square_location_id' => 'LOC-DB',
        'square_webhook_signature_key' => 'wh-db',
        'square_environment' => 'production',
    ]);

    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->twilioAccountSid())->toBe('AC-from-db')
        ->and($credentials->twilioCredentialSource())->toBe('database')
        ->and($credentials->squareEnvironment())->toBe('production')
        ->and($credentials->squareCredentialSource())->toBe('database');
});
