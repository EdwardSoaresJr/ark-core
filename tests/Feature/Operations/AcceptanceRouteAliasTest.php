<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('day review short path redirects to the owner day review route', function (): void {
    $owner = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($owner)
        ->get('/app/day-review')
        ->assertRedirect('/app/owner/day-review');

    $this->actingAs($owner)
        ->followingRedirects()
        ->get('/app/day-review')
        ->assertOk()
        ->assertSee('Day Review');
});

test('day review short path keeps owner gate after redirect', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->followingRedirects()
        ->get('/app/day-review')
        ->assertForbidden();
});

test('check-in alias redirects to the intake create workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get('/app/check-in')
        ->assertRedirect('/app/intake/new');

    $this->actingAs($advisor)
        ->followingRedirects()
        ->get('/app/check-in')
        ->assertOk()
        ->assertSee('Check In');
});

test('named acceptance aliases resolve to the supported destinations', function (): void {
    expect(route('operations.day-review', absolute: false))->toBe('/app/day-review')
        ->and(route('operations.check-in', absolute: false))->toBe('/app/check-in')
        ->and(route('operations.owner.day-review', absolute: false))->toBe('/app/owner/day-review')
        ->and(route('operations.intake.create', absolute: false))->toBe('/app/intake/new');
});
