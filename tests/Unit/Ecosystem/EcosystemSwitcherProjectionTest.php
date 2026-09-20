<?php

use App\Ark\Runtime\Ecosystem\EcosystemProduct;
use App\Ark\Runtime\Ecosystem\EcosystemSwitcherProjection;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Tests\TestCase;


beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config([
        'ark-ecosystem.operations_url' => 'https://app.test',
        'ark-ecosystem.arkademy_url' => 'https://learn.test',
        'ark-ecosystem.platform_url' => 'https://platform.test',
        'ark-ecosystem.shelf_slug' => 'shop-in-a-box',
        'bookstack.cutover' => true,
        'bookstack.base_url' => 'https://learn.test',
    ]);
});

test('admin sees operations arkademy and platform in switcher', function () {
    $user = User::factory()->create()->assignRole('admin');

    $items = app(EcosystemSwitcherProjection::class)->forUser($user, EcosystemProduct::Operations);

    expect($items)->toHaveCount(3)
        ->and(collect($items)->pluck('id')->all())->toEqual(['operations', 'arkademy', 'platform'])
        ->and($items[0]['current'])->toBeTrue();
});

test('advisor sees operations and arkademy without platform', function () {
    $user = User::factory()->create()->assignRole('advisor');

    $items = app(EcosystemSwitcherProjection::class)->forUser($user);

    expect($items)->toHaveCount(2)
        ->and(collect($items)->pluck('id')->all())->toEqual(['operations', 'arkademy']);
});

test('switcher hidden when only one product available', function () {
    $user = User::factory()->create()->assignRole('customer');

    $projection = app(EcosystemSwitcherProjection::class);

    expect($projection->forUser($user))->toBe([])
        ->and($projection->shouldRender($user))->toBeFalse();
});
