<?php

use Database\Seeders\ArkAuthorizationSeeder;

test('repair order builder loads the full-width Tabler workspace', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ark-tabler', false)
        ->assertSee('ark-tabler-ro', false)
        ->assertSee('page-header', false)
        ->assertSee('page-title', false)
        ->assertSee('nav nav-tabs', false)
        ->assertSee('ops-ro-identity-band', false)
        ->assertSee('ark-tabler-ro__table', false)
        ->assertSee('ark-tabler-payment', false)
        ->assertSee('Payment &amp; settlement', false)
        ->assertSee('Estimate balance')
        ->assertSee('Qty/Hrs')
        ->assertSee('Estimate total')
        ->assertSee('Amber Adams')
        ->assertSee('Acura');
});
