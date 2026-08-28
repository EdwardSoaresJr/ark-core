<?php

use App\Ark\Operations\Messaging\Messenger\MessengerShopConnection;
use App\Ark\Operations\Settings\CommunicationsChannelSettings;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\QueryException;

test('communications channels settings tab saves messenger page fields atomically', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    config()->set('services.meta_messenger.app_secret', 'platform-app-secret');
    config()->set('services.meta_messenger.verify_token', 'platform-verify-token');

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'messenger',
            'channels' => [
                'messenger' => [
                    'enabled' => '1',
                    'page_id' => '1122334455',
                    'page_name' => 'Demo Auto Repair',
                    'page_access_token' => 'EAABstub-token',
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'messenger',
        ]));

    $shopSettings = ShopSettings::current()->fresh();
    $settings = CommunicationsChannelSettings::fromShopSettings($shopSettings);
    $connection = MessengerShopConnection::forShop($shopSettings);

    expect($settings->messengerEnabled)->toBeTrue()
        ->and($settings->messengerPageId)->toBe('1122334455')
        ->and($settings->messengerPageName)->toBe('Demo Auto Repair')
        ->and($settings->messengerPageAccessToken)->toBe('EAABstub-token')
        ->and($shopSettings->messenger_page_id)->toBe('1122334455')
        ->and($shopSettings->messenger_page_access_token)->toBe('EAABstub-token')
        ->and($connection->isConfigured())->toBeTrue()
        ->and(data_get($shopSettings->communications_channels, 'messenger.page_access_token'))->toBeNull();
});

test('communications channels settings page shows health card without platform secrets', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    config()->set('services.meta_messenger.app_secret', 'platform-app-secret');
    config()->set('services.meta_messenger.verify_token', 'platform-verify-token');

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Admin))
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'messenger',
        ]))
        ->assertOk()
        ->assertSee('Facebook Messenger')
        ->assertSee('Enable Messenger ingress and queue')
        ->assertSee('Advanced · Page credentials')
        ->assertSee('Facebook Page ID')
        ->assertDontSee('Webhook verify token')
        ->assertDontSee('App secret')
        ->assertDontSee('Regenerate')
        ->assertSee(\App\Support\Branding\Branding::learnName())
        ->assertSee('Messenger setup')
        ->assertSee('data-arkademy-guide="admin:messenger-setup"', false);
});

test('messenger page id cannot belong to two shops', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->persistTrusted([
        'messenger_page_id' => 'page-shared',
        'communications_channels' => [
            'messenger' => [
                'enabled' => true,
                'page_id' => 'page-shared',
            ],
        ],
    ]);

    $second = new ShopSettings;
    $second->forceFill([
        'shop_name' => 'Second Shop',
        'messenger_page_id' => 'page-shared',
    ]);

    expect(fn () => $second->save())->toThrow(QueryException::class);
});
