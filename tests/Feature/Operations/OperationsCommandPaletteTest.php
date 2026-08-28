<?php

use App\Ark\Runtime\Authorization\ArkCapability;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('operations shell embeds command palette registry for advisors', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('data-ops-global-search', false)
        ->assertSee('data-ops-global-search-commands', false)
        ->assertSee('nav.workboard', false)
        ->assertSee('create.repair-order', false)
        ->assertSee('ops.print-key-tag', false);
});

test('settings commands stay hidden for advisors without settings manage', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    expect($advisor->can(ArkCapability::SettingsManage->value))->toBeFalse();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('nav.workboard', false)
        ->assertDontSee('nav.settings', false);
});
