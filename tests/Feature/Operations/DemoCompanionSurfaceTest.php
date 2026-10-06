<?php

use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('demo hides arkademy and platform surfaces', function () {
    config(['app.url' => 'https://demo.arksms.com']);
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.learn.index'))
        ->assertRedirect(route('operations.today'));

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('platform.clusters.index'))
        ->assertRedirect(route('operations.today'));

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', ['section' => 'ark-cloud']))
        ->assertOk()
        ->assertDontSee("setActive('ark-cloud')", false)
        ->assertDontSee('Connect in ARK Platform', false);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.today'))
        ->assertOk()
        ->assertDontSee('>ARKademy<', false)
        ->assertSee('ARK Public Demo', false)
        ->assertSee('View on GitHub', false)
        ->assertSee('Hosted ARK coming soon', false);
});

test('shops outside the demo still show arkademy and platform', function () {
    config(['app.url' => 'https://app.lugsnplugs.test']);
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertSee('ARK Platform', false)
        ->assertSee('>ARKademy<', false)
        ->assertDontSee('ARK Public Demo', false);
});
