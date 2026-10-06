<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['appointments_enabled' => true]);
});

test('phone navigation reuses the operations rail', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $html = $this->actingAs($advisor)
        ->get(route('operations.schedule'))
        ->assertOk()
        ->assertSee('data-ops-mobile-nav-open', false)
        ->assertSee('aria-controls="ops-mobile-nav"', false)
        ->assertSee('Close navigation', false)
        ->assertSee('Sign out', false)
        ->assertSee('Job Board', false)
        ->assertSee('Schedule', false)
        ->getContent();

    expect(substr_count($html, 'class="ops-rail-nav"'))->toBe(1)
        ->and(substr_count($html, 'data-ops-comms-nav-link'))->toBe(1)
        ->and(substr_count($html, 'data-ops-mobile-nav-open'))->toBe(1);
});

test('mobile navigation stays closed until the menu breakpoint', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@media (max-width: 767px)')
        ->and($css)->toContain('.ops-shell.is-mobile-nav-open .ops-left-rail')
        ->and($css)->toContain('body.ops-mobile-nav-lock');
});
