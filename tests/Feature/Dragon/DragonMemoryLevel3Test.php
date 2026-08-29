<?php

use App\Ark\Dragon\Agent\DragonAgentMemory;
use App\Ark\Dragon\Agent\DragonMemoryContext;
use App\Ark\Dragon\Agent\DragonModelTurn;
use App\Ark\Dragon\Agent\Providers\FakeDragonProvider;
use App\Ark\Dragon\Agent\RecallDragonMemory;
use App\Ark\Dragon\Agent\Tools\MemoryRecallTool;
use App\Ark\Dragon\Agent\Tools\ShopFinancialSnapshotTool;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Station\StationDeviceToken;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['shop.identity' => 'test.demo-auto.local']);
    config(['dragon.provider' => 'fake']);
});

function memoryAdmin(): array
{
    $user = User::factory()->create()->assignRole(ArkRole::Admin->value);

    return [$user, $user->createToken('desk')->plainTextToken];
}

function memoryWorkstation(string $name): Workstation
{
    return Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => $name,
        'location_label' => $name,
        'is_active' => true,
    ]);
}

test('explicit remember persists company memory and a new conversation can recall it', function (): void {
    [$user, $token] = memoryAdmin();

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Remember that we require loaded charging-system evidence before condemning an alternator.',
        ])
        ->assertOk()
        ->assertJsonPath('source', 'memory');

    expect(DragonAgentMemory::query()->whereNull('superseded_at')->count())->toBe(1);

    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn(null, [[
            'id' => 'call_mem',
            'name' => 'memory.recall',
            'arguments' => ['needle' => 'alternator'],
        ]]),
        new DragonModelTurn('Require loaded charging-system evidence before condemning an alternator.', []),
    ];

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', ['message' => 'What is our alternator standard?'])
        ->assertOk()
        ->assertJsonPath('traces.0.tool', 'memory.recall');

    $facts = app(MemoryRecallTool::class)->invoke(['needle' => 'alternator']);
    expect(json_encode($facts['facts']))->toContain('loaded charging-system evidence')
        ->and($facts['_trace']['result_count'])->toBe(1);
});

test('one-off diagnostic chat does not create durable memory', function (): void {
    [, $token] = memoryAdmin();
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn('Could be a parasitic drain. Check current draw before replacing the battery.', []),
    ];

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'I think this Subaru might have a parasitic drain tonight.',
        ])
        ->assertOk();

    expect(DragonAgentMemory::query()->count())->toBe(0);
});

test('memory.propose does not write until the coworker confirms', function (): void {
    [, $token] = memoryAdmin();
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn(null, [[
            'id' => 'call_p',
            'name' => 'memory.propose',
            'arguments' => [
                'fact' => 'Use evidence-first diagnostic explanations.',
                'scope_intent' => 'company',
                'category' => 'standard',
            ],
        ]]),
        new DragonModelTurn('Should I remember that as a company-wide shop standard?', []),
    ];

    $first = $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'We should always use evidence-first diagnostic explanations.',
        ])
        ->assertOk()
        ->json();

    expect(DragonAgentMemory::query()->count())->toBe(0);

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Yes',
            'conversation_id' => $first['conversation_id'],
        ])
        ->assertOk()
        ->assertJsonPath('source', 'memory');

    expect(DragonAgentMemory::query()->whereNull('superseded_at')->count())->toBe(1)
        ->and(DragonAgentMemory::query()->first()->fact_value)->toContain('evidence-first');
});

test('correction supersedes the old durable memory', function (): void {
    [$user, $token] = memoryAdmin();

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Remember that we always phrase charging findings as battery voltage only.',
        ])->assertOk();

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Change that. From now on we require loaded output and voltage-drop evidence when the symptom calls for it.',
        ])->assertOk();

    $active = DragonAgentMemory::query()->whereNull('superseded_at')->get();
    $inactive = DragonAgentMemory::query()->whereNotNull('superseded_at')->get();
    expect($active)->toHaveCount(1)
        ->and($inactive)->toHaveCount(1)
        ->and($active->first()->fact_value)->toContain('loaded output')
        ->and($inactive->first()->fact_value)->toContain('battery voltage only');
});

test('forget removes memory from recall', function (): void {
    [, $token] = memoryAdmin();
    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Remember that we call the alignment rack the Hunter.',
        ])->assertOk();

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Forget the rule about calling the alignment rack the Hunter.',
        ])->assertOk();

    $facts = app(MemoryRecallTool::class)->invoke(['needle' => 'Hunter']);
    expect($facts['facts'])->toBe([]);
});

test('location A memory does not leak into location B and company memory is shared', function (): void {
    $company = DragonAgentMemory::query()->create([
        'fact_key' => 'taught:'.Str::uuid(),
        'fact_value' => 'Use evidence-first diagnostic explanations.',
        'scope_type' => 'company',
        'category' => 'standard',
        'taught_by' => 'test',
        'provenance' => 'test',
    ]);
    $a = memoryWorkstation('Location A');
    $b = memoryWorkstation('Location B');
    DragonAgentMemory::query()->create([
        'fact_key' => 'taught:'.Str::uuid(),
        'fact_value' => 'At this location we call the alignment rack the Hunter.',
        'scope_type' => 'workstation',
        'workstation_id' => $a->id,
        'category' => 'terminology',
        'taught_by' => 'test',
        'provenance' => 'test',
    ]);
    DragonAgentMemory::query()->create([
        'fact_key' => 'taught:'.Str::uuid(),
        'fact_value' => 'At this location we call the alignment rack Rack 2.',
        'scope_type' => 'workstation',
        'workstation_id' => $b->id,
        'category' => 'terminology',
        'taught_by' => 'test',
        'provenance' => 'test',
    ]);

    $recall = app(RecallDragonMemory::class);
    $fromA = $recall->facts(null, new DragonMemoryContext(null, $a, null, 'test', 'staff'));
    $fromB = $recall->facts(null, new DragonMemoryContext(null, $b, null, 'test', 'staff'));
    $valuesA = collect($fromA)->pluck('value')->implode(' ');
    $valuesB = collect($fromB)->pluck('value')->implode(' ');

    expect($valuesA)->toContain('evidence-first')
        ->and($valuesA)->toContain('Hunter')
        ->and($valuesA)->not->toContain('Rack 2')
        ->and($valuesB)->toContain('evidence-first')
        ->and($valuesB)->toContain('Rack 2')
        ->and($valuesB)->not->toContain('Hunter')
        ->and($company->scope_type)->toBe('company');
});

test('live ARK financial truth beats a stale memory about waiting approval', function (): void {
    DragonAgentMemory::query()->create([
        'fact_key' => 'taught:'.Str::uuid(),
        'fact_value' => 'Waiting approval is $1.00.',
        'scope_type' => 'company',
        'category' => 'standard',
        'taught_by' => 'import',
        'provenance' => 'stale-import',
    ]);

    $snapshot = app(ShopFinancialSnapshotTool::class)->invoke(['range' => 'today']);
    $live = (string) ($snapshot['waiting_approval']['pending_recommended_display'] ?? '');
    [, $token] = memoryAdmin();
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn(null, [[
            'id' => 'c1',
            'name' => 'memory.recall',
            'arguments' => ['needle' => 'waiting approval'],
        ]]),
        new DragonModelTurn(null, [[
            'id' => 'c2',
            'name' => 'shop.financial_snapshot',
            'arguments' => ['range' => 'today'],
        ]]),
        new DragonModelTurn('Live waiting approval is '.$live.'. Memory is stale.', []),
    ];

    $response = $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', ['message' => 'How much is waiting approval?'])
        ->assertOk();

    expect($response->json('reply'))->not->toBe('Waiting approval is $1.00.')
        ->and($response->json('traces.1.tool'))->toBe('shop.financial_snapshot');
});

test('company memory taught on staff chat is recallable from a new Shop Glass conversation', function (): void {
    [, $token] = memoryAdmin();
    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Remember that we require loaded charging-system evidence before condemning an alternator.',
        ])->assertOk();

    $issued = StationDeviceToken::issue('glass-memory-cert', 'test.demo-auto.local');
    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn(null, [[
            'id' => 'stn_mem',
            'name' => 'memory.recall',
            'arguments' => ['needle' => 'alternator'],
        ]]),
        new DragonModelTurn('Loaded charging-system evidence before condemning an alternator.', []),
    ];

    $this->withToken($issued['plain_text'])
        ->postJson('/api/station/dragon/chat', ['message' => 'What would you tell Landon before condemning this alternator?'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $facts = app(MemoryRecallTool::class)->invoke(['needle' => 'alternator']);
    expect(json_encode($facts))->toContain('loaded charging-system');
});

test('user preference does not become company policy for another coworker', function (): void {
    $edward = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $molly = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->withToken($edward->createToken('desk')->plainTextToken)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Remember that I prefer the short version when I ask how the shop is doing.',
        ])->assertOk();

    $row = DragonAgentMemory::query()->whereNull('superseded_at')->first();
    expect($row?->scope_type)->toBe('user')->and($row?->user_id)->toBe($edward->id);

    $edwardFacts = app(RecallDragonMemory::class)->facts('short version', new DragonMemoryContext($edward, null, null, 'Edward', 'staff'));
    $mollyFacts = app(RecallDragonMemory::class)->facts('short version', new DragonMemoryContext($molly, null, null, 'Molly', 'staff'));
    expect(collect($edwardFacts)->pluck('value')->implode(' '))->toContain('short version')
        ->and(collect($mollyFacts)->pluck('value')->all())->toBe([]);
});

test('sensitive junk is rejected as durable memory', function (): void {
    [, $token] = memoryAdmin();
    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Remember that the customer card is 4242424242424242.',
        ])
        ->assertOk()
        ->assertJsonPath('source', 'memory');

    expect(DragonAgentMemory::query()->count())->toBe(0);
});

test('authorized staff can inspect and forget memory in settings', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $memory = DragonAgentMemory::query()->create([
        'fact_key' => 'taught:'.Str::uuid(),
        'fact_value' => 'Use evidence-first diagnostic explanations.',
        'scope_type' => 'company',
        'category' => 'standard',
        'taught_by' => 'Edward',
        'provenance' => 'test',
    ]);

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'dragon-memory']))
        ->assertOk()
        ->assertSee('Use evidence-first diagnostic explanations.', false);

    $this->actingAs($admin)
        ->post(route('operations.settings.shop.dragon-memory.forget', $memory))
        ->assertRedirect();

    expect($memory->fresh()->superseded_at)->not->toBeNull();
});

test('advisor cannot write company-wide memory', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->withToken($advisor->createToken('desk')->plainTextToken)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Remember that we never condemn an alternator from battery voltage alone.',
        ])
        ->assertOk();

    expect(DragonAgentMemory::query()->count())->toBe(0);
});

test('location remember uses the current workstation not a model-chosen id', function (): void {
    [$user, $token] = memoryAdmin();
    $here = memoryWorkstation('Demo City');
    $other = memoryWorkstation('Downtown');
    $here->forceFill(['current_operator_user_id' => $user->id])->save();

    $this->withToken($token)
        ->postJson('/api/dragon-agent/chat', [
            'message' => 'Remember for this location that we stage waiting-parts cars along the west wall.',
        ])->assertOk();

    $row = DragonAgentMemory::query()->whereNull('superseded_at')->first();
    expect($row?->scope_type)->toBe('workstation')
        ->and($row?->workstation_id)->toBe($here->id)
        ->and($row?->workstation_id)->not->toBe($other->id);
});
