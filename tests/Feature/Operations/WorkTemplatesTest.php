<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\WorkTemplates\ApplyWorkTemplateAction;
use App\Ark\Operations\WorkTemplates\WorkTemplate;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Cache;

function makeWorkTemplateFixture(string $title = 'Front Brake Service'): WorkTemplate
{
    $template = WorkTemplate::query()->create([
        'title' => $title,
        'description' => 'Pads and rotors',
        'position' => 1,
    ]);

    $template->lines()->create([
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front brake pads and rotors',
        'quantity' => '1.50',
        'position' => 1,
    ]);
    $template->lines()->create([
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'position' => 2,
    ]);
    $template->lines()->create([
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake rotor',
        'quantity' => '2.00',
        'position' => 3,
    ]);

    return $template->fresh('lines');
}

function makeWorkTemplateRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Template',
        'last_name' => 'Customer',
        'phone' => '555-0400',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Saved work test',
    ]);
}

test('shop can create a work template with labor and part lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($user)
        ->post(route('operations.settings.shop.work-templates.store'), [
            'title' => 'Front Brake Service',
            'description' => 'Common front brakes',
            'recommendation_intent' => 'immediate_attention',
            'lines' => [
                ['type' => 'labor', 'description' => 'Replace front brake pads and rotors', 'quantity' => '1.5'],
                ['type' => 'part', 'description' => 'Front brake pads', 'quantity' => '1'],
                ['type' => 'part', 'description' => 'Front brake rotor', 'quantity' => '2'],
            ],
        ])
        ->assertRedirect();

    $template = WorkTemplate::query()->where('title', 'Front Brake Service')->first();

    expect($template)->not->toBeNull()
        ->and($template->lines)->toHaveCount(3)
        ->and($template->recommendationIntent()->value)->toBe('immediate_attention')
        ->and($template->lines->pluck('type')->map->value->all())->toBe(['labor', 'part', 'part']);
});

test('shop persists saved work part cost, sell price, and fee', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($user)
        ->post(route('operations.settings.shop.work-templates.store'), [
            'title' => 'Priced Brake Job',
            'description' => 'With money',
            'recommendation_intent' => 'maintenance',
            'lines' => [
                ['type' => 'labor', 'description' => 'R&R pads', 'quantity' => '1.5', 'unit_price' => '185'],
                ['type' => 'part', 'description' => 'Front pads', 'quantity' => '1', 'unit_price' => '89.99', 'part_cost' => '42.50'],
                ['type' => 'fee', 'description' => 'Shop supplies', 'quantity' => '1', 'unit_price' => '15'],
            ],
        ])
        ->assertRedirect();

    $template = WorkTemplate::query()->where('title', 'Priced Brake Job')->first();

    expect($template)->not->toBeNull()
        ->and((int) $template->lines[0]->unit_price_cents)->toBe(18500)
        ->and((int) $template->lines[1]->unit_price_cents)->toBe(8999)
        ->and((int) $template->lines[1]->part_cost_cents)->toBe(4250)
        ->and((int) $template->lines[2]->unit_price_cents)->toBe(1500);
});

test('shop persists dollar strings with $ and commas on saved work lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($user)
        ->post(route('operations.settings.shop.work-templates.store'), [
            'title' => 'Dollar String Job',
            'description' => null,
            'recommendation_intent' => 'maintenance',
            'lines' => [
                ['type' => 'labor', 'description' => 'R&R pads', 'quantity' => '1.5', 'unit_price' => '$185.00'],
                ['type' => 'part', 'description' => 'Pads', 'quantity' => '1', 'unit_price' => '1,249.50', 'part_cost' => '$42.50'],
            ],
        ])
        ->assertRedirect();

    $template = WorkTemplate::query()->where('title', 'Dollar String Job')->first();

    expect($template)->not->toBeNull()
        ->and((int) $template->lines[0]->unit_price_cents)->toBe(18500)
        ->and((int) $template->lines[1]->unit_price_cents)->toBe(124950)
        ->and((int) $template->lines[1]->part_cost_cents)->toBe(4250);
});

test('applying saved work copies part sell, part cost, and fee onto the repair order', function () {
    $actor = User::factory()->create();
    $repairOrder = makeWorkTemplateRepairOrder();
    $template = WorkTemplate::query()->create([
        'title' => 'Priced Pads',
        'description' => null,
        'position' => 1,
    ]);
    $template->lines()->create([
        'type' => RepairOrderLineType::Labor,
        'description' => 'R&R pads',
        'quantity' => '1.00',
        'unit_price_cents' => 18500,
        'position' => 1,
    ]);
    $template->lines()->create([
        'type' => RepairOrderLineType::Part,
        'description' => 'Front pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8999,
        'part_cost_cents' => 4250,
        'position' => 2,
    ]);
    $template->lines()->create([
        'type' => RepairOrderLineType::Fee,
        'description' => 'Shop supplies',
        'quantity' => '1.00',
        'unit_price_cents' => 1500,
        'position' => 3,
    ]);

    $result = app(ApplyWorkTemplateAction::class)->handle($repairOrder, $template->fresh('lines'), $actor);
    $labor = $repairOrder->lines()->findOrFail($result['line_ids'][0]);
    $part = $repairOrder->lines()->findOrFail($result['line_ids'][1]);
    $fee = $repairOrder->lines()->findOrFail($result['line_ids'][2]);

    expect($labor->type)->toBe(RepairOrderLineType::Labor)
        ->and($labor->unit_price_cents)->toBe(18500)
        ->and($labor->labor_rate_cents)->toBe(18500)
        ->and($part->type)->toBe(RepairOrderLineType::Part)
        ->and($part->unit_price_cents)->toBe(8999)
        ->and($part->part_cost_cents)->toBe(4250)
        ->and($fee->type)->toBe(RepairOrderLineType::Fee)
        ->and($fee->unit_price_cents)->toBe(1500);
});

test('applying a template authors a repair action with ordinary lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = makeWorkTemplateRepairOrder();
    $template = makeWorkTemplateFixture();

    $result = app(ApplyWorkTemplateAction::class)->handle($repairOrder, $template, $actor);

    $workGroup = $result['work_group']->fresh('lines');

    expect($workGroup->title)->toBe('Front Brake Service')
        ->and($workGroup->created_from_template_id)->toBe($template->id)
        ->and($result['concern']->recommendationIntent()->value)->toBe('maintenance')
        ->and($workGroup->lines)->toHaveCount(3)
        ->and($workGroup->lines->first()->type)->toBe(RepairOrderLineType::Labor)
        ->and((float) $workGroup->lines->first()->quantity)->toBe(1.5)
        ->and($workGroup->lines->first()->unit_price_cents)->toBeGreaterThan(0);
});

test('template edit after apply does not alter existing repair order lines', function () {
    $actor = User::factory()->create();
    $repairOrder = makeWorkTemplateRepairOrder();
    $template = makeWorkTemplateFixture();

    $result = app(ApplyWorkTemplateAction::class)->handle($repairOrder, $template, $actor);
    $lineId = $result['line_ids'][0];
    $original = $repairOrder->lines()->findOrFail($lineId)->description;

    $template->lines()->where('type', RepairOrderLineType::Labor->value)->update([
        'description' => 'CHANGED AFTER APPLY',
    ]);
    $template->update(['title' => 'Renamed Template']);

    expect($repairOrder->fresh()->lines()->findOrFail($lineId)->description)->toBe($original)
        ->and($result['work_group']->fresh()->title)->toBe('Front Brake Service');
});

test('template retirement does not alter historical repair order work', function () {
    $actor = User::factory()->create();
    $repairOrder = makeWorkTemplateRepairOrder();
    $template = makeWorkTemplateFixture();

    $result = app(ApplyWorkTemplateAction::class)->handle($repairOrder, $template, $actor);
    $workGroupId = $result['work_group']->id;

    $template->retire();

    expect($result['work_group']->fresh()->title)->toBe('Front Brake Service')
        ->and($repairOrder->fresh()->lines()->where('repair_order_work_group_id', $workGroupId)->count())->toBeGreaterThan(0);
});

test('retired templates are excluded from saved work search', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $active = makeWorkTemplateFixture('Front Brake Service');
    $retired = makeWorkTemplateFixture('Retired Job');
    $retired->retire();

    $this->actingAs($user)
        ->getJson(route('operations.work-templates.search', ['q' => 'Brake']))
        ->assertOk()
        ->assertJsonPath('templates.0.title', 'Front Brake Service')
        ->assertJsonMissing(['title' => 'Retired Job']);

    expect($active->fresh()->isRetired())->toBeFalse();
});

test('applying under an existing concern places the repair action there', function () {
    $actor = User::factory()->create();
    $repairOrder = makeWorkTemplateRepairOrder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Customer brake noise',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);
    $template = makeWorkTemplateFixture();

    $result = app(ApplyWorkTemplateAction::class)->handle($repairOrder, $template, $actor, $concern);

    expect($result['concern']->id)->toBe($concern->id)
        ->and($result['work_group']->repair_order_concern_id)->toBe($concern->id)
        ->and($repairOrder->fresh()->concerns)->toHaveCount(1);
});

test('new concern from saved work uses template recommendation status unless overridden', function () {
    $actor = User::factory()->create();
    $repairOrder = makeWorkTemplateRepairOrder();
    $template = makeWorkTemplateFixture();
    $template->update(['recommendation_intent' => RecommendationIntent::ImmediateAttention->value]);

    $fromTemplate = app(ApplyWorkTemplateAction::class)->handle($repairOrder, $template, $actor);
    $overridden = app(ApplyWorkTemplateAction::class)->handle(
        $repairOrder,
        $template,
        $actor,
        null,
        null,
        RecommendationIntent::Diagnostic,
    );

    expect($fromTemplate['concern']->recommendationIntent())->toBe(RecommendationIntent::ImmediateAttention)
        ->and($overridden['concern']->recommendationIntent())->toBe(RecommendationIntent::Diagnostic);
});

test('attaching saved work does not rewrite an existing concern recommendation status', function () {
    $actor = User::factory()->create();
    $repairOrder = makeWorkTemplateRepairOrder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Check engine light',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => RecommendationIntent::Diagnostic->value,
        'position' => 1,
    ]);
    $template = makeWorkTemplateFixture();
    $template->update(['recommendation_intent' => RecommendationIntent::Maintenance->value]);

    $result = app(ApplyWorkTemplateAction::class)->handle(
        $repairOrder,
        $template,
        $actor,
        $concern,
        null,
        RecommendationIntent::ImmediateAttention,
    );

    expect($result['concern']->fresh()->recommendationIntent())->toBe(RecommendationIntent::Diagnostic);
});

test('apply endpoint can attach saved work to an existing concern', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = makeWorkTemplateRepairOrder();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => RecommendationIntent::ImmediateAttention->value,
        'position' => 1,
    ]);
    $template = makeWorkTemplateFixture();

    $this->actingAs($user)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.work-templates.apply', $repairOrder), [
            'work_template_id' => $template->id,
            'repair_order_concern_id' => $concern->id,
            WorksheetMutationIdempotency::FIELD => 'tpl-apply-existing-concern',
        ])
        ->assertRedirect();

    expect($repairOrder->fresh()->concerns)->toHaveCount(1)
        ->and($concern->fresh()->workGroups)->toHaveCount(1)
        ->and($concern->fresh()->recommendationIntent())->toBe(RecommendationIntent::ImmediateAttention);
});

test('apply endpoint is idempotent for the same worksheet key', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = makeWorkTemplateRepairOrder();
    $template = makeWorkTemplateFixture();

    $payload = [
        'work_template_id' => $template->id,
        WorksheetMutationIdempotency::FIELD => 'tpl-apply-test-1',
    ];

    $this->actingAs($user)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.work-templates.apply', $repairOrder), $payload)
        ->assertRedirect();

    $countAfterFirst = $repairOrder->fresh()->lines()->where('repair_order_work_group_id', '!=', null)->count();

    $this->actingAs($user)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.work-templates.apply', $repairOrder), $payload)
        ->assertRedirect();

    expect($repairOrder->fresh()->lines()->where('repair_order_work_group_id', '!=', null)->count())->toBe($countAfterFirst);
});

test('search finds templates by partial title', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    makeWorkTemplateFixture('Front Brake Service');
    makeWorkTemplateFixture('Spark Plug Replacement');

    $this->actingAs($user)
        ->getJson(route('operations.work-templates.search', ['q' => 'front bra']))
        ->assertOk()
        ->assertJsonCount(1, 'templates')
        ->assertJsonPath('templates.0.title', 'Front Brake Service');
});

test('labor from template uses shop labor pricing authority', function () {
    $actor = User::factory()->create();
    $repairOrder = makeWorkTemplateRepairOrder();
    $template = makeWorkTemplateFixture();

    $result = app(ApplyWorkTemplateAction::class)->handle($repairOrder, $template, $actor);
    $labor = $repairOrder->lines()->findOrFail($result['line_ids'][0]);

    expect($labor->type)->toBe(RepairOrderLineType::Labor)
        ->and($labor->labor_rate_cents)->not->toBeNull()
        ->and($labor->unit_price_cents)->toBe($labor->labor_rate_cents)
        ->and($labor->labor_policy_id)->not->toBeNull();
});
