<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    enableHostedPlatformPayments();

    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

test('hosted payments settings do not offer core terminal pairing', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('managed by ARK Platform', false)
        ->assertSee('Counter terminal', false)
        ->assertDontSee('Generate pairing code', false)
        ->assertDontSee('name="square_access_token"', false)
        ->assertDontSee('name="square_application_id"', false);
});
