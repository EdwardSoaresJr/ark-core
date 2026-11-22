<?php

use App\Ark\Operations\Settings\ShopPlatformSettingsController;
use App\Ark\Operations\Settings\ShopCommunicationsSettingsController;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
test('cloud and customer-messaging settings controllers load without estimate trait contracts', function () {
    expect(new ShopPlatformSettingsController)->toBeInstanceOf(ShopPlatformSettingsController::class)
        ->and(new ShopCommunicationsSettingsController)->toBeInstanceOf(ShopCommunicationsSettingsController::class);
});

test('ark cloud connect action refuses Box pairing from Core Settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->post(route('operations.settings.shop.ark-cloud.connect'))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'general']));
});

test('customer messaging update does not fatal on controller load', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $catalog = enableHostedTenantCatalog();
    fakePlatformVoiceStatus('active', runtimeOwner: 'core');

    try {
        $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
            ->patch(route('operations.settings.shop.customer-messaging.update'), [
                'postmark_reply_to' => 'reply@example.test',
            ])
            ->assertRedirect();
    } finally {
        clearHostedTenantCatalog($catalog);
    }
});
