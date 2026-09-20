<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\QueryException;

test('legacy messenger settings url redirects away from removed core transport ui', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Admin))
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'messenger',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'customer-messaging']));
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
