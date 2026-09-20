<?php

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Schema;

test('communications settings ignore submitted twilio account credentials', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

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
    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($settings->telephony_inbound_number)->toBe('+17195550100')
        ->and($credentials->twilioAccountSid())->toBeNull()
        ->and($credentials->hasStoredTwilioAuthToken())->toBeFalse();
});

test('payments settings save capture surfaces without merchant credentials', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->persistTrusted([
        'square_application_id' => 'sq0idp-existing',
        'square_access_token' => 'existing-token',
        'square_location_id' => 'LOC-EXISTING',
        'square_webhook_signature_key' => 'wh-existing',
        'square_environment' => 'production',
        'square_enabled' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.payments.update'), [
            'square_application_id' => 'sq0idp-test-app',
            'square_access_token' => 'sq-test-access-token',
            'square_location_id' => 'LOC-TEST',
            'square_webhook_signature_key' => 'wh-test-key',
            'square_environment' => 'sandbox',
            'square_enabled' => '0',
            'square_terminal_device_id' => 'DEVICE-PREFERRED',
            'square_terminal_enabled' => '1',
            'square_keyed_enabled' => '1',
            'square_portal_pay_enabled' => '1',
            'square_email_pay_enabled' => '1',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']));

    $settings = ShopSettings::current()->fresh();

    expect($settings->square_application_id)->toBe('sq0idp-existing')
        ->and($settings->square_access_token)->toBe('existing-token')
        ->and($settings->square_location_id)->toBe('LOC-EXISTING')
        ->and($settings->square_webhook_signature_key)->toBe('wh-existing')
        ->and($settings->square_environment)->toBe('production')
        ->and((bool) $settings->square_enabled)->toBeTrue()
        ->and($settings->square_terminal_device_id)->toBe('DEVICE-PREFERRED')
        ->and((bool) $settings->square_terminal_enabled)->toBeTrue();
});

test('payments settings leave leftover merchant secrets unchanged', function () {
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
    $cleared = [
        'square_application_id' => null,
        'square_access_token' => null,
        'square_location_id' => null,
        'square_webhook_signature_key' => null,
        'square_environment' => null,
    ];

    if (Schema::hasColumn('shop_settings', 'postmark_token')) {
        $cleared['postmark_token'] = null;
    }

    ShopSettings::current()->persistTrusted($cleared);

    config()->set('services.twilio.account_sid', 'AC-env-only');
    config()->set('services.twilio.auth_token', 'token-env-only');
    config()->set('services.square.application_id', 'sq-env-app');
    config()->set('services.square.access_token', 'sq-env-token');
    config()->set('services.square.location_id', 'LOC-ENV');
    config()->set('services.partstech.username', 'parts-env');
    config()->set('services.partstech.password', 'parts-secret');
    config()->set('services.postmark.token', 'postmark-env-token');

    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->twilioAccountSid())->toBeNull()
        ->and($credentials->twilioCredentialSource())->toBe('none');
});

test('email settings save shop reply-to without postmark credentials', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    config()->set('services.postmark.token', null);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.email.update'), [
            'postmark_token' => 'postmark-server-token',
            'postmark_reply_to' => 'service@example.com',
            'postmark_reply_to_name' => 'Example Shop',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->postmark_reply_to)->toBe('service@example.com')
        ->and($settings->postmark_reply_to_name)->toBe('Example Shop');

    if (Schema::hasColumn('shop_settings', 'postmark_token')) {
        expect($settings->postmark_token)->not->toBe('postmark-server-token');
    }
});

test('hosted email settings hide postmark credentials and keep reply-to', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    enableHostedPlatformMail();

    $this->get(route('operations.settings.shop.edit', [
        'section' => 'communications',
        'communications-tab' => 'email',
    ]))
        ->assertOk()
        ->assertSee('Customer email')
        ->assertSee('Reply-To')
        ->assertSee('name="postmark_reply_to"', false)
        ->assertSee('Save reply-to settings', false)
        ->assertDontSee('name="postmark_token"', false)
        ->assertDontSee('Postmark email', false)
        ->assertDontSee('Server token', false);
});

test('hosted email settings save reply-to and leave leftover postmark secrets unchanged', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    enableHostedPlatformMail();

    if (Schema::hasColumn('shop_settings', 'postmark_token')) {
        ShopSettings::current()->persistTrusted([
            'postmark_token' => 'leftover-shop-postmark',
        ]);
    }

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.email.update'), [
            'postmark_token' => 'should-not-save-token',
            'postmark_reply_to' => 'service@example.com',
            'postmark_reply_to_name' => 'Example Shop',
            'postmark_message_stream_id' => 'should-not-save-stream',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->postmark_reply_to)->toBe('service@example.com')
        ->and($settings->postmark_reply_to_name)->toBe('Example Shop');

    if (Schema::hasColumn('shop_settings', 'postmark_token')) {
        expect($settings->postmark_token)->toBe('leftover-shop-postmark');
    }
});

test('integration settings pages omit dead provider credentials', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.settings.shop.edit', ['section' => 'communications']))
        ->assertOk()
        ->assertSee('Texting')
        ->assertDontSee('Account SID')
        ->assertDontSee('Auth token');

    $this->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('Preferred terminal')
        ->assertSee('Counter terminal')
        ->assertDontSee('Application ID')
        ->assertDontSee('Access token')
        ->assertDontSee('Webhook signature key');

    $this->get(route('operations.settings.shop.edit', ['section' => 'partstech']))
        ->assertOk()
        ->assertSee('PartsTech')
        ->assertSee('RepairLink')
        ->assertSee('Launch URL');

    $this->get(route('operations.settings.shop.edit', [
        'section' => 'communications',
        'communications-tab' => 'email',
    ]))
        ->assertOk()
        ->assertSee('Customer email')
        ->assertSee('Reply-To')
        ->assertDontSee('Server token');
});

test('shop integration credentials prefer database square values over env fallback', function () {
    config()->set('services.twilio.account_sid', 'AC-from-env');
    config()->set('services.twilio.auth_token', 'token-from-env');
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

    $settings = ShopSettings::current()->fresh();
    $credentials = ShopIntegrationCredentials::forCurrentShop();

    expect($credentials->twilioAccountSid())->toBeNull()
        ->and($settings->square_environment)->toBe('production')
        ->and($settings->square_application_id)->toBe('sq-db-app');
});

test('hosted payments settings keep square secrets and save capture surfaces', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    enableHostedPlatformPayments();

    ShopSettings::current()->persistTrusted([
        'square_application_id' => 'sq0idp-existing',
        'square_access_token' => 'existing-token',
        'square_location_id' => 'LOC-EXISTING',
        'square_webhook_signature_key' => 'wh-existing',
        'square_environment' => 'production',
        'square_enabled' => true,
        'square_terminal_device_id' => 'DEVICE-EXISTING',
        'square_terminal_enabled' => false,
        'square_keyed_enabled' => false,
        'square_portal_pay_enabled' => false,
        'square_email_pay_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.payments.update'), [
            'square_application_id' => 'sq0idp-should-not-save',
            'square_access_token' => 'should-not-save-token',
            'square_location_id' => 'LOC-SHOULD-NOT-SAVE',
            'square_webhook_signature_key' => 'wh-should-not-save',
            'square_environment' => 'sandbox',
            'square_enabled' => '0',
            'square_terminal_device_id' => 'DEVICE-PREFERRED',
            'square_terminal_enabled' => '1',
            'square_keyed_enabled' => '1',
            'square_portal_pay_enabled' => '1',
            'square_email_pay_enabled' => '1',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']));

    $settings = ShopSettings::current()->fresh();

    expect($settings->square_application_id)->toBe('sq0idp-existing')
        ->and($settings->square_access_token)->toBe('existing-token')
        ->and($settings->square_location_id)->toBe('LOC-EXISTING')
        ->and($settings->square_webhook_signature_key)->toBe('wh-existing')
        ->and($settings->square_environment)->toBe('production')
        ->and((bool) $settings->square_enabled)->toBeTrue()
        ->and($settings->square_terminal_device_id)->toBe('DEVICE-PREFERRED')
        ->and((bool) $settings->square_terminal_enabled)->toBeTrue()
        ->and((bool) $settings->square_keyed_enabled)->toBeTrue()
        ->and((bool) $settings->square_portal_pay_enabled)->toBeTrue()
        ->and((bool) $settings->square_email_pay_enabled)->toBeTrue();
});
