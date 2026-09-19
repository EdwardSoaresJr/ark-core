<?php

use App\Ark\Operations\Payments\FakeSquarePaymentsClient;
use App\Ark\Operations\Payments\ProcessSquareWebhookAction;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');

    ShopSettings::current()->update([
        'square_application_id' => 'sq0idp-test-app',
        'square_access_token' => 'test-token',
        'square_location_id' => 'LOC123',
        'square_environment' => 'production',
    ]);

    $this->fakeSquare = new FakeSquarePaymentsClient;
    $this->app->instance(FakeSquarePaymentsClient::class, $this->fakeSquare);
    $this->app->bind(\App\Ark\Operations\Payments\Contracts\SquarePaymentsClient::class, fn () => $this->fakeSquare);

    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

test('settings can generate a square terminal pairing code in production', function () {
    $this->actingAs($this->admin)
        ->post(route('operations.settings.shop.payments.square-terminal-device-code.store'), [
            'name' => 'Front counter',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertSessionHas('square_terminal_pairing.code', 'TRXKEB')
        ->assertSessionHas('status');

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('Generate pairing code', false)
        ->assertSee('action="'.route('operations.settings.shop.payments.square-terminal-device-code.store').'"', false)
        ->assertSee('Save Square settings', false);
});

test('sandbox environment blocks pairing code generation', function () {
    ShopSettings::current()->update([
        'square_environment' => 'sandbox',
    ]);

    $this->actingAs($this->admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->post(route('operations.settings.shop.payments.square-terminal-device-code.store'))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertSessionHasErrors('square');
});

test('paired device code status saves terminal device id', function () {
    $create = $this->actingAs($this->admin)
        ->post(route('operations.settings.shop.payments.square-terminal-device-code.store'))
        ->assertRedirect();

    $deviceCodeId = $create->getSession()->get('square_terminal_pairing.id');

    $this->fakeSquare->pairTerminalDeviceCode($deviceCodeId, 'R5WNWB5BKNG9R');

    $this->actingAs($this->admin)
        ->getJson(route('operations.settings.shop.payments.square-terminal-device-code.show', ['deviceCodeId' => $deviceCodeId]))
        ->assertOk()
        ->assertJsonPath('paired', true)
        ->assertJsonPath('device_id', 'R5WNWB5BKNG9R');

    expect(ShopSettings::current()->fresh()->square_terminal_device_id)->toBe('R5WNWB5BKNG9R')
        ->and((bool) ShopSettings::current()->fresh()->square_terminal_enabled)->toBeTrue();
});

test('device code paired webhook saves terminal device id', function () {
    app(ProcessSquareWebhookAction::class)->execute([
        'type' => 'device.code.paired',
        'data' => [
            'object' => [
                'device_code' => [
                    'device_id' => 'R5WNWB5BKNG9R',
                    'status' => 'PAIRED',
                ],
            ],
        ],
    ]);

    expect(ShopSettings::current()->fresh()->square_terminal_device_id)->toBe('R5WNWB5BKNG9R')
        ->and((bool) ShopSettings::current()->fresh()->square_terminal_enabled)->toBeTrue();
});

test('hosted payments settings refuse core terminal pairing', function () {
    enableHostedPlatformPayments();

    $this->actingAs($this->admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->post(route('operations.settings.shop.payments.square-terminal-device-code.store'), [
            'name' => 'Front counter',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertSessionHasErrors('square_terminal_pairing');

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertDontSee('Generate pairing code', false)
        ->assertDontSee('Save Square API credentials here', false)
        ->assertDontSee('name="square_access_token"', false)
        ->assertDontSee('name="square_application_id"', false)
        ->assertSee('Counter terminal', false)
        ->assertSee('managed by ARK Platform', false);
});
