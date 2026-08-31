<?php

use App\Ark\Operations\Messaging\Messenger\MessengerShopConnection;
use App\Ark\Operations\Settings\CommunicationsChannelSettings;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\QueryException;

test('communications channels settings tab saves messenger enabled flag', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'messenger',
            'channels' => [
                'messenger' => [
                    'enabled' => '1',
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
        ->and($connection->isEnabled())->toBeTrue()
        ->and($connection->isConfigured())->toBeFalse();
});

test('communications channels settings page shows messenger not configured state', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Admin))
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'messenger',
        ]))
        ->assertOk()
        ->assertSee('Facebook Messenger')
        ->assertSee('Not configured')
        ->assertSee('Show Messenger in inbound queue filters')
        ->assertDontSee('Page access token')
        ->assertDontSee('Advanced · Page credentials');
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
