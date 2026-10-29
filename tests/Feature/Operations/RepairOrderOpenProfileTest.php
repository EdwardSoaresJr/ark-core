<?php

use App\Ark\Operations\Diagnostics\QueryCompositionFixtures;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('opening a repair order reuses settlement catalog and messaging reads', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();

    $measured = measureQueries(function () use ($repairOrder) {
        return $this->get(route('operations.repair-orders.show', $repairOrder))
            ->assertOk()
            ->assertSee('+ Add Work', false)
            ->assertSee('id="estimate-lines"', false)
            ->assertSee('id="workspace-modal-host"', false)
            ->assertSee('Customer View', false);
    });

    expect($measured['count'])->toBeLessThanOrEqual(60);
});
