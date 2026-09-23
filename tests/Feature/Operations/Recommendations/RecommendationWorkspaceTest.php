<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Recommendations\CreateRecommendationAction;
use App\Ark\Operations\Recommendations\RecommendationUrgency;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('repair order workspace exposes primary advisor workspaces', function () {
    $repairOrder = workspaceTabRepairOrder();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($actor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Estimate')
        ->assertSee('Inspection')
        ->assertSee('Recommendations')
        ->assertSee('Communications')
        ->assertSee('History')
        ->assertDontSee('>Portal</button>', false)
        ->assertDontSee('>Auth</button>', false)
        ->assertDontSee('>Parts</button>', false)
        ->assertSee('data-workspace-tab-panel="recommendations"', false)
        ->assertSee('Authorization')
        ->assertSee('Customer View')
        ->assertSee('Labor Guide', false)
        ->assertSee('ops-estimate-context-rail', false)
        ->assertSee('ops-estimate-build-toolbar', false)
        ->assertSee('data-toolbar-group="labor-guide"', false)
        ->assertSee('+ Add Work');
});

test('recommendations empty state is a single sentence without a zero count', function () {
    $repairOrder = workspaceTabRepairOrder();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($actor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'recommendations',
        ]))
        ->assertOk()
        ->assertSee('What this vehicle still needs')
        ->assertSee('No open recommendations for this vehicle.')
        ->assertSee('Record recommendation')
        ->assertDontSee('0 open');
});

test('recommendations workspace tab lists open vehicle recommendations', function () {
    $repairOrder = workspaceTabRepairOrder();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $repairOrder->customer,
        'vehicle' => $repairOrder->vehicle,
        'title' => 'Replace LF outer tie rod',
        'originating_repair_order' => $repairOrder,
        'urgency' => RecommendationUrgency::Now,
        'safety_related' => true,
    ]);

    $this->actingAs($actor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'recommendations',
        ]))
        ->assertOk()
        ->assertSee('What this vehicle still needs')
        ->assertSee('Replace LF outer tie rod')
        ->assertSee('Safety')
        ->assertSee('Add to Estimate')
        ->assertSee('Present')
        ->assertSee('Decision')
        ->assertSee('Follow-up')
        ->assertSee('Resolve')
        ->assertSee('History')
        ->assertSee('Record recommendation')
        ->assertSee(route('operations.repair-orders.recommendations.present', [$repairOrder, $recommendation]), false)
        ->assertSee(route('operations.repair-orders.recommendations.decision', [$repairOrder, $recommendation]), false)
        ->assertSee(route('operations.repair-orders.recommendations.follow-up', [$repairOrder, $recommendation]), false)
        ->assertSee(route('operations.repair-orders.recommendations.resolve', [$repairOrder, $recommendation]), false)
        ->assertSee(route('operations.repair-orders.recommendations.dismiss', [$repairOrder, $recommendation]), false);
});

test('legacy portal and auth tab endpoints still render', function () {
    $repairOrder = workspaceTabRepairOrder(withPart: true);
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($actor)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'portal']))
        ->assertOk()
        ->assertSee('Customer Portal')
        ->assertSee('Preview customer estimate');

    $this->actingAs($actor)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'auth']))
        ->assertOk()
        ->assertSee('Customer Authorization')
        ->assertSee('Authorization History');
});

test('estimate page still records authorization from the moved estimate surface', function () {
    $repairOrder = workspaceTabRepairOrder();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($actor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('name="approved_by"', false)
        ->assertSee(route('operations.repair-orders.authorization.store', $repairOrder), false);
});

test('labor guide intent does not require provider credentials', function () {
    $repairOrder = workspaceTabRepairOrder();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    config()->set('rte-labor-guide.enabled', false);
    \App\Ark\Operations\LaborGuides\Rte\RteLaborGuideAvailability::forgetCachedState();

    $this->actingAs($actor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Labor Guide', false)
        ->assertDontSee('Repair Time Engine', false);
});
