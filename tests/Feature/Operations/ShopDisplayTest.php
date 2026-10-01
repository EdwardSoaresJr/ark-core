<?php

use App\Ark\Operations\Display\ShopDisplayBoardProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('shop display requires authenticated staff', function () {
    $this->get(route('operations.display'))
        ->assertRedirect(route('login'));
});

test('shop display renders ambient projection without workflow chrome', function () {
    decisionPressureRepairOrder(
        firstName: 'Lam',
        lastName: 'Tran',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 204_930,
    );

    decisionPressureRepairOrder(
        firstName: 'Ready',
        lastName: 'Pickup',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 124_700,
    );

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.display'))
        ->assertOk()
        ->assertSee('Active Cars', false)
        ->assertSee('Needs Action', false)
        ->assertSee('Active Work', false)
        ->assertSee('Ready Pickup', false)
        ->assertSee('Lam Tran', false)
        ->assertSee('ops-shop-display-card__status--waiting-approval', false)
        ->assertDontSee('Search job board', false)
        ->assertDontSee('ops-attention-card__action', false)
        ->assertDontSee('>Call<', false);
});

test('shop display fragment returns board partial for polling refresh', function () {
    decisionPressureRepairOrder(
        firstName: 'Display',
        lastName: 'Refresh',
        status: RepairOrderStatus::InProgress,
        lineCents: 99_677,
    );

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.display.fragment'))
        ->assertOk()
        ->assertSee('ops-shop-display__header', false)
        ->assertSee('Display Refresh', false)
        ->assertDontSee('ops-shop-display-body', false);
});

test('shop display projection consumes advisor home attention board', function () {
    decisionPressureRepairOrder(
        firstName: 'Projection',
        lastName: 'Check',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 150_000,
    );

    $display = ShopDisplayBoardProjection::resolve(
        app(\App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery::class),
        app(\App\Ark\Operations\Workboard\WorkboardTriageProjection::class),
        app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class),
        app(\App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection::class),
        app(\App\Ark\Operations\Today\AdvisorHomeAttentionBoardProjection::class),
    );

    expect($display->activeCarCount)->toBeGreaterThan(0)
        ->and($display->attentionZones)->not->toBeEmpty();
});
