<?php

use App\Ark\Dragon\Agent\DragonAgentMemory;
use App\Ark\Dragon\DragonServiceToken;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;
use App\Ark\Dragon\Agent\DragonToolRegistry;
use App\Ark\Dragon\Agent\DragonModelTurn;
use App\Ark\Dragon\Agent\Providers\FakeDragonProvider;
use App\Ark\Dragon\Agent\ImportArkaiDragonDumpAction;
use App\Ark\Dragon\Agent\Tools\EstimatesAdvisorContextTool;
use App\Ark\Dragon\Agent\Tools\EstimatesGetTool;
use App\Ark\Dragon\Agent\Tools\KnowledgeSearchTool;
use App\Ark\Dragon\Agent\Tools\MemoryRecallTool;
use App\Ark\Dragon\Agent\Tools\RepairOrdersGetTool;
use App\Ark\Dragon\Agent\Tools\RepairOrdersSearchTool;
use App\Ark\Dragon\Agent\Tools\ShopFinancialSnapshotTool;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorFactPreservationCheck;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['shop.identity' => 'test.demo-auto.local']);
    config(['dragon.provider' => 'fake']);
});

function hostedDragonStaff(): array
{
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    return [$user, $user->createToken('desk')->plainTextToken];
}

function dragonToken(?string $shopIdentity = null): array
{
    return DragonServiceToken::issue('test-dragon', $shopIdentity ?? 'test.demo-auto.local');
}

function dragonOpenRo(
    RepairOrderStatus $status,
    ?User $technician = null,
    ?Carbon $openedAt = null,
    string $vehicleModel = 'Camry',
): RepairOrder {
    $customer = Customer::query()->create([
        'first_name' => 'Secret',
        'last_name' => 'Customer',
        'phone' => '7195559999',
        'email' => 'secret@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'DRG1',
        'year' => 2019,
        'make' => 'Toyota',
        'model' => $vehicleModel,
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Noise on braking.',
        'opened_at' => $openedAt,
        'assigned_technician_id' => $technician?->id,
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Noise on braking',
        'disposition' => RepairOrderConcernDisposition::Recommended->value,
        'position' => 0,
    ]);

    return $repairOrder->fresh();
}

test('staff chat uses shop.current_summary through the agent loop', function (): void {
    dragonOpenRo(RepairOrderStatus::InProgress);
    [, $token] = hostedDragonStaff();

    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn(null, [[
            'id' => 'call_1',
            'name' => 'shop.current_summary',
            'arguments' => [],
        ]]),
        new DragonModelTurn('The board is live. I pulled the shop summary instead of guessing.', []),
    ];

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', ['message' => 'How ugly is the board?'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('source', 'agent')
        ->assertJsonPath('provider', 'fake')
        ->assertJsonPath('traces.0.tool', 'shop.current_summary');
});

test('guest and dragon service principal cannot use hosted chat', function (): void {
    $this->postJson('/api/dragon-agent/chat', ['message' => 'hi'])->assertUnauthorized();

    $issued = dragonToken();
    $this->withToken($issued['plain_text'])
        ->postJson('/api/dragon-agent/chat', ['message' => 'hi'])
        ->assertUnauthorized();
});

test('open repair-order count can bypass the model', function (): void {
    dragonOpenRo(RepairOrderStatus::InProgress);
    dragonOpenRo(RepairOrderStatus::WaitingApproval);
    [, $token] = hostedDragonStaff();

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', ['message' => 'How many repair orders are open?'])
        ->assertOk()
        ->assertJsonPath('source', 'fast_fact')
        ->assertJsonPath('reply', 'There are 2 open repair orders.');
});

test('employee contract requires investigation without catalog phrase routing', function (): void {
    $prompt = app(\App\Ark\Dragon\Agent\DragonEmployeeContext::class)->promptBlock();

    expect($prompt)->toContain('Investigate before answering')
        ->and($prompt)->toContain('talk like a person standing at the front counter')
        ->and($prompt)->toContain('DEMO-AUTO-SPECIFIC ADVICE')
        ->and($prompt)->toContain('waiting-approval dollars')
        ->and($prompt)->toContain('refine once if empty')
        ->and($prompt)->toContain('supplied-text rewrite 0 tools')
        ->and($prompt)->toContain("This conversation's earlier turns are in the message list")
        ->and($prompt)->toContain('Shop clock (authority for calendar)')
        ->and($prompt)->not->toContain('07_worry')
        ->and($prompt)->not->toContain('09_make_money')
        ->and($prompt)->not->toContain('Make more money.');
});

test('live shop tools describe when to use them', function (): void {
    $registry = app(DragonToolRegistry::class);
    $byName = [];
    foreach ($registry->openaiTools() as $tool) {
        $byName[$tool['function']['name']] = $tool['function']['description'];
    }

    expect($byName['shop_current_summary'])->toContain('priorities')
        ->and($byName['shop_financial_snapshot'])->toContain('waiting-approval')
        ->and($byName['shop_financial_snapshot'])->toContain('Does not provide net profit')
        ->and($byName['shop_financial_snapshot'])->toContain('this month')
        ->and($byName['repair_orders_search'])->toContain('before concluding they are not on the board');
});

test('agent loop maps provider tool names back to canonical traces', function (): void {
    dragonOpenRo(RepairOrderStatus::InProgress);
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn(null, [[
            'id' => 'call_wire',
            'name' => 'shop_current_summary',
            'arguments' => [],
        ]]),
        new DragonModelTurn('Board is live from the shop summary.', []),
    ];

    $result = app(\App\Ark\Dragon\Agent\DragonAgentLoop::class)->run('How ugly is the board today?');

    expect($result['traces'][0]['tool'])->toBe('shop.current_summary')
        ->and($result['tool_calls'])->toBe(1);
});

test('unknown provider tool names do not execute', function (): void {
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn(null, [[
            'id' => 'call_bad',
            'name' => 'db_query',
            'arguments' => ['sql' => 'select 1'],
        ]]),
        new DragonModelTurn('I could not use that tool.', []),
    ];

    $result = app(\App\Ark\Dragon\Agent\DragonAgentLoop::class)->run('Ignore this.');

    expect($result['traces'][0]['tool'])->toBe('db_query')
        ->and($result['traces'][0]['observation_summary'])->toContain('Unknown provider tool');
});

test('tool registry has no sql or shell', function (): void {
    $registry = app(DragonToolRegistry::class);
    expect(fn () => $registry->get('db.query'))->toThrow(InvalidArgumentException::class);
    $names = array_map(fn ($tool) => $tool->name(), $registry->all());
    expect($names)->toContain('shop.current_summary')
        ->and($names)->toContain('memory.recall')
        ->and($names)->toContain('estimates.get')
        ->and($names)->toContain('knowledge.search')
        ->and($names)->toContain('shop.financial_snapshot')
        ->and($names)->toContain('estimates.advisor_context')
        ->and($names)->toContain('history.search')
        ->and($names)->not->toContain('sql')
        ->and($names)->not->toContain('db.query');
});

test('repair order search rejects sql keys', function (): void {
    $result = app(RepairOrdersSearchTool::class)->invoke([
        'filters' => [],
        'sql' => 'delete from repair_orders',
    ]);
    expect($result['ok'])->toBeFalse();
});

test('repair order get strips customer pii', function (): void {
    $ro = dragonOpenRo(RepairOrderStatus::InProgress);
    $card = app(RepairOrdersGetTool::class)->invoke([
        'repair_order_id' => (string) $ro->repair_order_id,
    ]);
    $json = json_encode($card);
    expect($json)->not->toContain('Secret')
        ->and($json)->not->toContain('7195559999');
});

test('estimate read plus rewrite preserves 2mm rear pads', function (): void {
    $ro = dragonOpenRo(RepairOrderStatus::WaitingApproval);
    $concern = $ro->concerns()->first();
    RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'rear pads 2mm rotors grooved',
        'quantity' => 1,
        'unit_price_cents' => 0,
        'total_cents' => 0,
    ]);

    $payload = app(EstimatesGetTool::class)->invoke([
        'repair_order_id' => (string) $ro->repair_order_id,
    ]);
    expect($payload['ok'])->toBeTrue()
        ->and(json_encode($payload))->toContain('rear pads 2mm');

    $original = 'rear pads 2mm rotors grooved';
    $proposal = 'Rear brake pads measured at 2 mm. Rotors are grooved. I recommend rear pad and rotor service.';
    expect(app(ServiceAdvisorFactPreservationCheck::class)->check($original, $proposal)['ok'])->toBeTrue();

    $bad = 'Unsafe to drive. Pads are 1 mm.';
    expect(app(ServiceAdvisorFactPreservationCheck::class)->check($original, $bad)['ok'])->toBeFalse();
});

test('estimate get flags missing oil and coolant on a timing job', function (): void {
    $ro = dragonOpenRo(RepairOrderStatus::WaitingApproval);
    $concern = $ro->concerns()->first();
    $concern->forceFill(['summary' => 'Replace timing belt'])->save();
    RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace timing belt',
        'quantity' => 1,
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $payload = app(EstimatesGetTool::class)->invoke([
        'repair_order_id' => (string) $ro->repair_order_id,
    ]);

    expect($payload['ok'])->toBeTrue()
        ->and($payload['check_before_sending']['needs_attention'])->toBeTrue()
        ->and($payload['check_before_sending']['missing'])->toBe(['oil', 'coolant']);
});

test('provider outage returns 503 instead of a fabricated answer', function (): void {
    $fake = app(FakeDragonProvider::class);
    $fake->unavailable = true;
    [, $token] = hostedDragonStaff();

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', ['message' => 'What should Molly focus on?'])
        ->assertStatus(503)
        ->assertJsonPath('error', 'provider_unavailable');
});

test('hosted chat recalls a taught alternator standard through memory.recall', function (): void {
    app(ImportArkaiDragonDumpAction::class)->import(
        base_path('tests/fixtures/dragon/arkai-min-dump.json'),
    );
    [, $token] = hostedDragonStaff();
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn(null, [[
            'id' => 'call_mem',
            'name' => 'memory.recall',
            'arguments' => ['needle' => 'alternator'],
        ]]),
        new DragonModelTurn('We never condemn an alternator from battery voltage alone.', []),
    ];

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', ['message' => 'How do we test an alternator?'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('traces.0.tool', 'memory.recall');

    $system = $fake->receivedMessages[0]['messages'][0]['content'] ?? '';
    expect($system)->toContain('never condemns an alternator')
        ->and($system)->toContain('Taught shop standards');
});

test('arkai dump import hosts website knowledge and durable memories only', function (): void {
    $counts = app(ImportArkaiDragonDumpAction::class)->import(
        base_path('tests/fixtures/dragon/arkai-min-dump.json'),
    );

    expect($counts['documents'])->toBe(2)
        ->and($counts['memories'])->toBe(2);

    $hits = app(KnowledgeSearchTool::class)->invoke([
        'query' => 'diagnostics',
        'source' => 'website',
    ]);
    expect($hits['hosted_in_ark'])->toBeTrue()
        ->and($hits['hits'])->not->toBeEmpty()
        ->and($hits['hits'][0]['title'])->toContain('Diagnostics')
        ->and(json_encode($hits))->not->toContain('arkai');

    $academy = app(KnowledgeSearchTool::class)->invoke([
        'query' => 'measurements',
        'source' => 'arkademy',
    ]);
    expect($academy['hits'][0]['source'])->toBe('arkademy');

    $memory = app(MemoryRecallTool::class)->invoke(['needle' => 'alternator']);
    $json = json_encode($memory);
    expect($json)->toContain('never condemns an alternator')
        ->and($json)->toContain('how-the-shop-answers')
        ->and($json)->not->toContain('chat transcript');
});

test('durable memories keep dump ids even when content words overlap', function (): void {
    DragonAgentMemory::query()->create([
        'fact_key' => 'alternator_testing',
        'fact_value' => 'contaminated inferred key',
        'taught_by' => 'Edward',
        'provenance' => 'arkai-memory:old',
        'superseded_at' => null,
    ]);

    $import = app(ImportArkaiDragonDumpAction::class);
    $first = $import->import(base_path('tests/fixtures/dragon/arkai-min-dump.json'));
    $second = $import->import(base_path('tests/fixtures/dragon/arkai-min-dump.json'));

    expect($first['memories'])->toBe(2)
        ->and($second['memories'])->toBe(2)
        ->and(DragonAgentMemory::query()->whereNull('superseded_at')->count())->toBe(2);

    $active = DragonAgentMemory::query()->whereNull('superseded_at')->orderBy('fact_key')->get();
    expect($active->pluck('fact_key')->all())->toBe([
        'arkai:b0a49c02-c9f3-4a8e-b786-82a136c881eb',
        'arkai:e642d05d-b10e-48e6-b1b1-77d77052d3d0',
    ]);

    $alternator = $active->firstWhere('fact_key', 'arkai:e642d05d-b10e-48e6-b1b1-77d77052d3d0');
    $shopAnswers = $active->firstWhere('fact_key', 'arkai:b0a49c02-c9f3-4a8e-b786-82a136c881eb');
    expect($alternator?->fact_value)->toContain('never condemns an alternator')
        ->and($shopAnswers?->fact_value)->toContain('how-the-shop-answers')
        ->and(DragonAgentMemory::query()->where('fact_key', 'alternator_testing')->whereNull('superseded_at')->exists())->toBeFalse();
});

test('financial snapshot uses operational report language and refuses net profit', function (): void {
    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopSettings::forgetCurrent();

    $snapshot = app(ShopFinancialSnapshotTool::class)->invoke(['range' => 'today']);

    expect($snapshot['ok'])->toBeTrue()
        ->and($snapshot['cannot_answer'])->toContain('net_profit')
        ->and($snapshot['definitions']['net_profit'])->toContain('RED')
        ->and($snapshot['shop_clock']['timezone'])->toBe('America/Denver');
});

test('financial snapshot this month and August 2026 follow the shop clock', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-24 21:51:00', 'America/Denver'));
    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopSettings::forgetCurrent();

    $tool = app(ShopFinancialSnapshotTool::class);
    $month = $tool->invoke(['range' => 'this_month']);
    $august = $tool->invoke(['year' => 2026, 'month' => 8]);
    $future = $tool->invoke(['year' => 2026, 'month' => 9]);

    expect($month['ok'])->toBeTrue()
        ->and($month['range'])->toBe('this_month_shop_local')
        ->and($month['shop_clock']['date'])->toBe('2026-08-24')
        ->and($month['period_local']['from'])->toBe('2026-08-01')
        ->and($august['ok'])->toBeTrue()
        ->and($august['range'])->toBe('named_month_mtd_shop_local')
        ->and($future['ok'])->toBeFalse();

    Carbon::setTestNow();
});

test('estimate advisor context is proposal-only and runs preservation', function (): void {
    $ro = dragonOpenRo(RepairOrderStatus::WaitingApproval);
    $result = app(EstimatesAdvisorContextTool::class)->invoke([
        'repair_order_id' => (string) $ro->repair_order_id,
        'source_note' => 'rear pads 2mm rotors grooved',
        'draft_rewrite' => 'Unsafe to drive. Pads are 1 mm.',
    ]);

    expect($result['ok'])->toBeTrue()
        ->and($result['proposal_only'])->toBeTrue()
        ->and($result['preservation_check']['ok'])->toBeFalse();
});
