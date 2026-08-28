<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('canonical repair order exposes full presentation workspace tabs', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    concernForEstimateWorkspace($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('ops-ro-workspace-tabs--builder-only', false)
        ->assertSee("selectTab('comms')", false)
        ->assertSee("selectTab('inspect')", false)
        ->assertSee("selectTab('parts')", false)
        ->assertSee("selectTab('portal')", false)
        ->assertSee("selectTab('history')", false);
});

test('waiting-approval repair order still exposes full workspace tab rail', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    concernForEstimateWorkspace($repairOrder);
    $repairOrder->update(['status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('ops-ro-workspace-tabs--builder-only', false)
        ->assertSee("selectTab('comms')", false)
        ->assertSee("selectTab('inspect')", false);
});
