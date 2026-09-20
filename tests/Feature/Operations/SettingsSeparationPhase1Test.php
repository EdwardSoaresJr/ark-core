<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

test('communications general settings omit twilio account credentials', function (): void {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'general',
        ]))
        ->assertOk()
        ->assertSee('Texting')
        ->assertSee('ARK Texting is not connected', false)
        ->assertDontSee('name="twilio_account_sid"', false)
        ->assertDontSee('name="twilio_auth_token"', false)
        ->assertDontSee('Account SID', false)
        ->assertDontSee('Auth token', false)
        ->assertSee('Canned responses', false)
        ->assertSee('Test incoming call', false);
});

test('communications general save does not write twilio account credentials', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'general',
            'telephony_inbound_number' => '+17195550100',
            'twilio_account_sid' => 'AC-should-not-save',
            'twilio_auth_token' => 'secret-should-not-save',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'general',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->telephony_inbound_number)->toBe('+17195550100');

    if (Schema::hasColumn('shop_settings', 'twilio_account_sid')) {
        expect($settings->twilio_account_sid)->not->toBe('AC-should-not-save');
    }
});

test('communications email tab loads reply-to without postmark credentials', function (): void {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]))
        ->assertOk()
        ->assertSee('Customer email')
        ->assertSee('Reply-To')
        ->assertSee('name="postmark_reply_to"', false)
        ->assertSee('Save reply-to settings', false)
        ->assertDontSee('name="postmark_token"', false)
        ->assertDontSee('Server token', false)
        ->assertDontSee('Last incoming call', false);
});

test('communications email tab saves shop reply-to', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.email.update'), [
            'postmark_reply_to' => 'service@example.com',
            'postmark_reply_to_name' => 'Example Shop',
            'postmark_token' => 'should-not-save-token',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'email',
        ]));

    $settings = ShopSettings::current()->fresh();

    expect($settings->postmark_reply_to)->toBe('service@example.com')
        ->and($settings->postmark_reply_to_name)->toBe('Example Shop');

    if (Schema::hasColumn('shop_settings', 'postmark_token')) {
        expect($settings->postmark_token)->not->toBe('should-not-save-token');
    }
});

test('disconnected square settings omit merchant credentials and missing webhook route', function (): void {
    expect(Route::has('webhooks.square'))->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('Card capture is not connected', false)
        ->assertSee('Record Payment still works', false)
        ->assertSee('Counter terminal', false)
        ->assertSee('Counter keyed entry', false)
        ->assertSee('Customer portal pay', false)
        ->assertSee('Emailed invoice pay link', false)
        ->assertSee('Preferred terminal', false)
        ->assertSee('name="square_terminal_device_id"', false)
        ->assertDontSee('name="square_access_token"', false)
        ->assertDontSee('name="square_application_id"', false)
        ->assertDontSee('Webhook URL', false)
        ->assertDontSee('Generate pairing code', false)
        ->assertDontSee('Save Square API credentials here', false);
});

test('payment settings save capture surfaces without merchant credentials', function (): void {
    ShopSettings::current()->persistTrusted([
        'square_application_id' => 'sq0idp-existing',
        'square_enabled' => true,
        'square_terminal_device_id' => 'DEVICE-EXISTING',
        'square_terminal_enabled' => false,
        'square_keyed_enabled' => false,
        'square_portal_pay_enabled' => false,
        'square_email_pay_enabled' => false,
    ]);

    $this->actingAs($this->admin)
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
        ->and((bool) $settings->square_enabled)->toBeTrue()
        ->and($settings->square_terminal_device_id)->toBe('DEVICE-PREFERRED')
        ->and((bool) $settings->square_terminal_enabled)->toBeTrue()
        ->and((bool) $settings->square_keyed_enabled)->toBeTrue()
        ->and((bool) $settings->square_portal_pay_enabled)->toBeTrue()
        ->and((bool) $settings->square_email_pay_enabled)->toBeTrue();
});

test('hosted square settings still hide provider secrets and keep capture surfaces', function (): void {
    enableHostedPlatformPayments();

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('managed by ARK Platform', false)
        ->assertSee('Counter terminal', false)
        ->assertSee('Take Payment', false)
        ->assertSee('Preferred terminal', false)
        ->assertDontSee('name="square_access_token"', false)
        ->assertDontSee('Generate pairing code', false)
        ->assertDontSee('Webhook URL', false);
});
