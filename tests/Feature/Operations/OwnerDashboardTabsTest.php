<?php

use App\Ark\Operations\Scoreboard\ShopOperatingScoreboardAccess;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('owner navigation uses dashboard and keeps scoreboard off the rail', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.dashboard'))
        ->assertOk()
        ->assertSee('<span>Dashboard</span>', false)
        ->assertDontSee('<span>Today</span>', false)
        ->assertDontSee('<span>Scoreboard</span>', false)
        ->assertSee('href="'.route('operations.dashboard').'"', false)
        ->assertDontSee('href="'.route('operations.owner.scoreboard').'" class="ops-rail-link', false);
});

test('dashboard defaults to today and exposes both tabs', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.dashboard'))
        ->assertOk()
        ->assertSee('Shop Dashboard')
        ->assertSee('aria-label="Dashboard"', false)
        ->assertSeeInOrder([
            'ops-ro-workspace-tab--active',
            '>Today</a>',
            '>Scoreboard</a>',
        ], false);
});

test('today route still renders the today dashboard tab', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Shop Dashboard')
        ->assertSee('Estimates to finish')
        ->assertSeeInOrder([
            'ops-ro-workspace-tab--active',
            '>Today</a>',
            'href="'.route('operations.owner.scoreboard').'"',
            '>Scoreboard</a>',
        ], false);
});

test('scoreboard tab renders the existing scoreboard and the old url stays compatible', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard'))
        ->assertOk()
        ->assertSee('Shop scoreboard')
        ->assertSee('Dollar close')
        ->assertSee('Authorized backlog')
        ->assertSee('Not authorized')
        ->assertSeeInOrder([
            '>Today</a>',
            'ops-ro-workspace-tab--active',
            '>Scoreboard</a>',
        ], false);

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['display' => 'wall']))
        ->assertOk()
        ->assertDontSee('aria-label="Dashboard"', false);

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['fragment' => '1']))
        ->assertOk()
        ->assertDontSee('ops-ro-workspace-tab', false);
});

test('dashboard tabs follow existing scoreboard permissions', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $operational = User::factory()->create();
    $operational->givePermissionTo([
        ArkCapability::OperationsAccess->value,
        ArkCapability::ScoreboardOperationalView->value,
    ]);

    expect(ShopOperatingScoreboardAccess::allows($technician))->toBeFalse()
        ->and(ShopOperatingScoreboardAccess::allows($operational))->toBeTrue();

    $this->actingAs($technician)
        ->get(route('operations.dashboard'))
        ->assertOk()
        ->assertDontSee('aria-label="Dashboard"', false)
        ->assertDontSee('>Scoreboard<', false);

    $this->actingAs($technician)
        ->get(route('operations.owner.scoreboard'))
        ->assertForbidden();

    $this->actingAs($operational)
        ->get(route('operations.owner.scoreboard'))
        ->assertOk()
        ->assertSee('Shop scoreboard')
        ->assertSee('ROs / open day')
        ->assertDontSee('Dollar close')
        ->assertSeeInOrder([
            '>Today</a>',
            'ops-ro-workspace-tab--active',
            '>Scoreboard</a>',
        ], false);
});
