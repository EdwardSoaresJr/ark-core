<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

function repairOrderForWorkspaceModalBuilder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Modal',
        'last_name' => 'Builder',
        'phone' => '5550100999',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Workspace modal builder',
    ]);
}

test('builder loads without edit view toggle and exposes add work authoring', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForWorkspaceModalBuilder();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('data-ro-mode-control', false)
        ->assertDontSee('>Editing<', false)
        ->assertDontSee('>Viewing<', false)
        ->assertDontSee('Workspace Modal', false)
        ->assertSee('+ Add Work', false)
        ->assertSee('data-workspace-modal-trigger="add-work"', false)
        ->assertSee('id="workspace-modal-host"', false)
        ->assertSee('What would you like to add?', false)
        ->assertSee('Customer Concern', false)
        ->assertSee('Engine Oil Service', false)
        ->assertSee('Testing Package', false)
        ->assertSee('name="add_work_choice"', false)
        ->assertDontSee('+ Add Engine Oil Service', false)
        ->assertDontSee('+ Authorize Testing Package', false)
        ->assertDontSee('No evidence yet. Attach photos', false)
        ->assertDontSee('id="evidence-gallery"', false);
});

test('repair action compose opens line types through authoring entry points', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForWorkspaceModalBuilder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes grinding',
        'position' => 1,
    ]);
    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'title' => 'Replace front pads',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front pads',
        'quantity' => '1.50',
        'unit_price_cents' => 15000,
        'total_cents' => 22500,
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        // Labor already anchors this Repair Action — still offer Labor for additional
        // hours lines, plus supporting part/note/sublet/evidence types.
        ->assertSee('Add Labor', false)
        ->assertSee('Add Part', false)
        ->assertSee('Add Note', false)
        ->assertSee('Add Sublet', false)
        ->assertSee('Add Photo', false)
        ->assertSee('ops-repair-action__compose-label', false)
        ->assertSee("workGroupId: {$workGroup->id}", false)
        ->assertSee('+ Common Job', false)
        ->assertSee("task: 'saved-work'", false)
        ->assertSee("concernId: {$concern->id}", false)
        ->assertDontSee('+ Scope note', false)
        ->assertDontSee('aria-label="Add scope note"', false)
        ->assertDontSee('ops-repair-action__scope-note-inline', false)
        ->assertDontSee('id="line-store-work-group-'.$workGroup->id.'"', false)
        ->getContent();

    expect($html)->toContain('ark-workspace-modal-open')
        ->and($html)->toContain('data-workspace-modal-form="line-create"')
        ->and($html)->toContain("workGroupId: {$workGroup->id}");
});

test('diagnostic concern without repair action still exposes note compose on the concern', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForWorkspaceModalBuilder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Full inspection like a pre purchase inspection.',
        'recommendation_intent' => 'diagnostic',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Comprehensive Vehicle Assessment',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'total_cents' => 16500,
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-scope-compose="'.$concern->id.'"', false)
        ->assertSee('id="workspace-line-create-concern-'.$concern->id.'"', false)
        ->assertSee("concernId: {$concern->id}", false)
        ->assertSee('Add Note', false)
        ->assertSee('Add Labor', false)
        ->getContent();

    expect($html)->toContain("task: 'note'")
        ->and($html)->toContain('lineType: \'note\'')
        ->and($html)->not->toContain('workGroupId:');

    $scopeCompose = substr($html, strpos($html, 'data-scope-compose="'.$concern->id.'"'), 6000);
    $laborPosition = strpos($scopeCompose, 'aria-label="Add Labor"');
    $commonJobPosition = strpos($scopeCompose, 'aria-label="Add Common Job"');
    $partPosition = strpos($scopeCompose, 'aria-label="Add Part"');

    expect($commonJobPosition)->not->toBeFalse()
        ->and($laborPosition)->not->toBeFalse()
        ->and($partPosition)->not->toBeFalse()
        ->and($commonJobPosition)->toBeLessThan($laborPosition)
        ->and($laborPosition)->toBeLessThan($partPosition);
});

test('standalone concern notes read as Concern Note while repair action notes read as Note', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForWorkspaceModalBuilder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Starter intermittent',
        'position' => 1,
    ]);
    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'title' => 'Replace Starter',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => null,
        'type' => RepairOrderLineType::Note,
        'description' => 'Customer states problem only occurs after rain.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'total_cents' => 0,
        'visible_to_advisor' => true,
        'visible_to_technician' => false,
        'visible_to_customer' => false,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Waiting on customer-supplied starter.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'total_cents' => 0,
        'visible_to_advisor' => true,
        'visible_to_technician' => true,
        'visible_to_customer' => false,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Concern Note', false)
        ->assertSee('Customer states problem only occurs after rain.', false)
        ->assertSee('Waiting on customer-supplied starter.', false);
});

test('concern creation through existing authority still succeeds from builder', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForWorkspaceModalBuilder();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'Customer hears grinding',
            'observed_summary' => 'Customer hears grinding',
        ])
        ->assertRedirect();

    expect(
        RepairOrderConcern::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('summary', 'Customer hears grinding')
            ->exists()
    )->toBeTrue();
});

test('edit line workspace modal exposes delete for destructive capability', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForWorkspaceModalBuilder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes grinding',
        'position' => 1,
    ]);
    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'title' => 'Replace front pads',
        'position' => 1,
    ]);
    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front pads',
        'quantity' => '1.50',
        'unit_price_cents' => 15000,
        'total_cents' => 22500,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]))
        ->assertOk()
        ->assertSee('data-workspace-modal-delete-line', false)
        ->assertSee(route('operations.repair-orders.lines.destroy', [$repairOrder, $line]), false)
        ->assertSee('Confirm delete', false);
});

test('builder presents remaining editors as presentation cards into workspace modals', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForWorkspaceModalBuilder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes grinding',
        'customer_states' => 'Noise on hard stops',
        'position' => 1,
    ]);
    RepairOrderWorkGroup::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'title' => 'Replace front pads',
        'position' => 1,
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-builder-present-card', false)
        ->assertSee("task: 'visit-reason'", false)
        ->assertSee("task: 'concern-narrative'", false)
        ->assertSee("task: 'concern-disposition'", false)
        ->assertSee("task: 'concern-billing'", false)
        ->assertSee("task: 'repair-action-meta'", false)
        ->assertSee('ops-repair-action__meta', false)
        ->assertSee('data-workspace-modal-form="visit-reason"', false)
        ->assertSee('data-workspace-modal-form="concern-narrative"', false)
        ->assertSee('data-workspace-modal-bundle="repair-action-meta"', false)
        ->assertDontSee('Narrative tools', false)
        ->assertDontSee('Save Narrative', false)
        ->getContent();

    // One primary Add Work — contextual footer (not a body CTA competing in the same viewport).
    expect(substr_count($html, 'id="workspace-visit-reason"'))->toBe(1)
        ->and(substr_count($html, 'data-workspace-modal-trigger="add-work"'))->toBe(1)
        ->and($html)->toContain('data-ro-footer')
        ->and($html)->not->toContain('id="builder-add-work"')
        ->and($html)->not->toContain('ops-review-toolbar-group" data-toolbar-group="scope"');
});

test('builder workspace modal keeps dormant rose text in Dragon slots without treating them as field errors', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForWorkspaceModalBuilder();
    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Noise',
        'position' => 1,
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('id="workspace-modal-host"')
        ->toContain('text-rose-700')
        ->toContain('x-show="errorMessage"')
        ->not->toContain('data-workspace-modal-validation');
});

test('builder Dragon generate is labeled Generate and is not gated on empty field lists', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForWorkspaceModalBuilder();
    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes grinding',
        'position' => 1,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee("x-text=\"phase === 'generating' ? 'Dragon drafting…' : 'Generate'\"", false)
        ->assertDontSee('Generate rewrite', false)
        ->assertSee('Save &amp; Generate', false)
        ->assertDontSee(':disabled="phase === \'generating\' || fields.length === 0"', false)
        ->assertSee(':disabled="! canGenerate"', false);
});

test('storing a line returns Saved flash and the created line id on the worksheet', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForWorkspaceModalBuilder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Pads',
        'position' => 1,
    ]);
    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'title' => 'Replace pads',
        'position' => 1,
    ]);

    $html = $this->actingAs($advisor)
        ->followingRedirects()
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'repair_order_work_group_id' => $workGroup->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Replace front pads',
            'quantity' => '1.00',
            'unit_price' => '150.00',
        ])
        ->assertOk()
        ->assertSee('data-worksheet-server-status', false)
        ->assertSee('Saved', false)
        ->getContent();

    $line = RepairOrderLine::query()->where('description', 'Replace front pads')->firstOrFail();

    expect($html)->toContain('data-ark-line-id="'.$line->id.'"');
});

