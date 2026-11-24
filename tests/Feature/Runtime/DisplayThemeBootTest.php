<?php

use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('operations shell boots display theme in head before assets', function () {
    $user = User::factory()->create(['display_theme' => 'dark'])->assignRole('advisor');

    $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

    expect($html)->toContain('name="ark-display-theme" content="dark"')
        ->and($html)->toContain("setAttribute('data-bs-theme'")
        ->and($html)->toContain('data-ark-display-theme-preference');

    $metaPos = strpos($html, 'name="ark-display-theme"');
    $bodyPos = stripos($html, '<body');
    expect($metaPos)->toBeInt()
        ->and($bodyPos)->toBeInt()
        ->and($metaPos)->toBeLessThan($bodyPos)
        ->and($html)->toContain('data-bs-theme');
});

test('system display theme preference is preserved in shell meta', function () {
    $user = User::factory()->create(['display_theme' => 'system'])->assignRole('advisor');

    $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

    expect($html)->toContain('name="ark-display-theme" content="system"')
        ->and($html)->toContain("preference === 'system'");
});
