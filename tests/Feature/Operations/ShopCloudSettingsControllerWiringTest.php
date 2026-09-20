<?php

use App\Ark\Operations\Settings\ShopPlatformSettingsController;
use App\Ark\Operations\Settings\ShopCommunicationsSettingsController;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

test('cloud and customer-messaging settings controllers load without estimate trait contracts', function () {
    expect(new ShopPlatformSettingsController)->toBeInstanceOf(ShopPlatformSettingsController::class)
        ->and(new ShopCommunicationsSettingsController)->toBeInstanceOf(ShopCommunicationsSettingsController::class);
});

test('ark cloud connect action opens browser connection continuation', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config([
        'services.ark_platform.base_url' => 'https://cloud.example.test',
        'services.ark_cloud.base_url' => 'https://cloud.example.test',
        'services.ark_mail.base_url' => 'https://cloud.example.test',
    ]);

    Http::fake([
        'cloud.example.test/api/v1/pairing/start' => Http::response([
            'ok' => true,
            'pairing_code' => 'ABCD1234',
            'public_id' => '00000000-0000-4000-8000-000000000099',
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ], 200),
    ]);

    $response = $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->post(route('operations.settings.shop.ark-cloud.connect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('https://cloud.example.test/connect/00000000-0000-4000-8000-000000000099');
});

test('customer messaging update does not fatal on controller load', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->patch(route('operations.settings.shop.customer-messaging.update'), [
            'postmark_reply_to' => 'reply@example.test',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'customer-messaging']));
});
