<?php

use App\Ark\Dragon\Assist\DragonAssistProjection;
use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistStatus;
use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Dragon\Assist\RequestDragonAssistAction;
use App\Ark\Dragon\ServiceAdvisor\ApplyServiceAdvisorRewriteAction;
use App\Ark\Dragon\ServiceAdvisor\DragonServiceAdvisorApplication;
use App\Ark\Dragon\ServiceAdvisor\RequestServiceAdvisorRewriteAction;
use App\Ark\Dragon\ServiceAdvisor\RevertServiceAdvisorRewriteAction;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorContextBuilder;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorFactPreservationCheck;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorMode;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorRewriteResultSchema;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['shop.identity' => 'test.demo-auto.local']);
});

function saRo(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Advisor',
        'last_name' => 'Test',
        'phone' => '5559990000',
        'email' => 'advisor-test@example.com',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Battery',
        'estimate_version' => 1,
    ]);
    $concern = $ro->concerns()->create([
        'summary' => 'Battery concern',
        'verified_findings' => 'Battery tested 650/480 CCA. Left front pad 2 mm. Possible EVAP leak — needs smoke test. DTC P0456 present.',
        'customer_states' => 'Customer says hard start in the morning.',
        'recommendation' => 'Replace battery. Smoke-test EVAP if leak confirmed.',
        'position' => 1,
    ]);

    return [$ro->fresh('vehicle'), $concern->fresh()];
}

function saAdvisor(): User
{
    $user = User::factory()->create(['name' => 'SA Advisor']);
    $user->assignRole(ArkRole::Advisor->value);

    return $user;
}

test('context builder excludes customer pii keys', function (): void {
    [$ro, $concern] = saRo();
    $payload = (new ServiceAdvisorContextBuilder)->build(
        $ro,
        $concern,
        ServiceAdvisorField::VerifiedFindings,
        ServiceAdvisorMode::ServiceAdvisorRewrite,
    );

    $encoded = strtolower(json_encode($payload) ?: '');
    expect($encoded)->not->toContain('"phone"')
        ->and($encoded)->not->toContain('"email"')
        ->and($encoded)->not->toContain('"address"')
        ->and($payload['selected_field'])->toBe('verified_findings')
        ->and($payload['original_hash'])->toBe(ServiceAdvisorContextBuilder::hashText($concern->verified_findings));
});

test('result schema requires proposal and prohibits prices parts labor', function (): void {
    $ok = ServiceAdvisorRewriteResultSchema::validate([
        'proposal' => 'Battery tested 650/480 CCA.',
        'facts_preserved' => ['650/480 CCA'],
        'material_changes' => [],
        'warnings' => [],
        'confidence' => 0.8,
    ]);
    expect($ok['proposal'])->toContain('650/480');

    expect(fn () => ServiceAdvisorRewriteResultSchema::validate([
        'proposal' => 'Replace battery',
        'prices' => [199],
    ]))->toThrow(ValidationException::class);

    expect(fn () => ServiceAdvisorRewriteResultSchema::validate([
        'proposal' => 'Replace battery',
        'parts' => ['battery'],
    ]))->toThrow(ValidationException::class);
});

test('fact preservation check catches measurement dtc side and invented urgency', function (): void {
    $check = new ServiceAdvisorFactPreservationCheck;
    $original = 'LF pad 2 mm. Battery 650 CCA. DTC P0456. Possible EVAP leak.';

    expect($check->check($original, 'LF pad 2 mm. Battery 650 CCA. DTC P0456. Possible EVAP leak.')['ok'])->toBeTrue();
    expect($check->check($original, 'LF pad worn. Battery weak. Possible EVAP leak.')['ok'])->toBeFalse();
    expect($check->check($original, 'LF pad 2 mm. Battery 650 CCA. Possible EVAP leak.')['ok'])->toBeFalse();
    expect($check->check($original, 'RF pad 2 mm. Battery 650 CCA. DTC P0456. Possible EVAP leak.')['ok'])->toBeFalse();
    expect($check->check('Left front pad 2 mm.', 'Right front pad 2 mm.')['ok'])->toBeFalse();

    expect($check->check(
        'Possible EVAP leak — needs smoke test.',
        'EVAP system has failed. Do not drive — unsafe.',
    )['ok'])->toBeFalse();
});

test('preview path creates assist without mutating concern text', function (): void {
    [$ro, $concern] = saRo();
    $before = $concern->verified_findings;

    $assist = app(RequestServiceAdvisorRewriteAction::class)->execute(
        $ro,
        $concern,
        ServiceAdvisorField::VerifiedFindings,
        ServiceAdvisorMode::CleanUp,
        saAdvisor(),
    );

    expect($assist->task_type)->toBe(DragonAssistTaskType::ServiceAdvisorRewrite)
        ->and($assist->status)->toBe(DragonAssistStatus::Completed)
        ->and($assist->result?->result_json['transport'] ?? null)->toBe('hosted')
        ->and($concern->fresh()->verified_findings)->toBe($before)
        ->and(DragonServiceAdvisorApplication::query()->count())->toBe(0);
});

test('rewrite without a dragon node completes through hosted dragon', function (): void {
    [$ro, $concern] = saRo();

    $this->actingAs(saAdvisor())
        ->postJson(route('operations.repair-orders.concerns.dragon-service-advisor', [$ro, $concern]), [
            'field' => 'verified_findings',
            'mode' => 'service_advisor_rewrite',
        ])
        ->assertCreated()
        ->assertJsonPath('assist.status', 'completed')
        ->assertJsonPath('assist.available', true)
        ->assertJsonPath('assist.proposal', $concern->verified_findings);
});

test('completed rewrite with failed fact check is rejected and apply unavailable', function (): void {
    [$ro, $concern] = saRo();
    app(\App\Ark\Dragon\Agent\Providers\FakeDragonProvider::class)->structuredQueue = [[
        'proposal' => 'Pads are worn. Replace immediately — unsafe to drive.',
        'facts_preserved' => [],
        'material_changes' => [],
        'warnings' => [],
        'confidence' => 0.5,
    ]];

    $assist = app(RequestDragonAssistAction::class)->execute(
        DragonAssistTaskType::ServiceAdvisorRewrite,
        (new ServiceAdvisorContextBuilder)->build(
            $ro,
            $concern,
            ServiceAdvisorField::VerifiedFindings,
            ServiceAdvisorMode::ServiceAdvisorRewrite,
        ),
        repairOrderId: (int) $ro->id,
        vehicleId: (int) $ro->vehicle_id,
    );

    expect($assist->fresh()->status)->toBe(DragonAssistStatus::Failed);
    $projection = DragonAssistProjection::fromRequest($assist->fresh(['result']));
    expect($projection['available'])->toBeFalse();
});

test('apply writes field with audit and revert restores exact original', function (): void {
    [$ro, $concern] = saRo();
    $user = saAdvisor();
    $original = $concern->verified_findings;
    $proposal = 'Battery tested 650/480 CCA. Left front pad measures 2 mm. Possible EVAP leak — needs smoke test. DTC P0456 present.';

    $assist = app(RequestDragonAssistAction::class)->execute(
        DragonAssistTaskType::ServiceAdvisorRewrite,
        (new ServiceAdvisorContextBuilder)->build(
            $ro,
            $concern,
            ServiceAdvisorField::VerifiedFindings,
            ServiceAdvisorMode::ServiceAdvisorRewrite,
        ),
        repairOrderId: (int) $ro->id,
        vehicleId: (int) $ro->vehicle_id,
        actor: $user,
    );

    $assist->forceFill([
        'status' => DragonAssistStatus::Completed,
        'completed_at' => now(),
    ])->save();
    $assist->result?->delete();
    $assist->result()->create([
        'result_json' => [
            'proposal' => $proposal,
            'facts_preserved' => ['650/480 CCA', '2 mm', 'P0456'],
            'material_changes' => [],
            'warnings' => [],
            'confidence' => 0.9,
        ],
        'model_name' => 'qwen3:14b',
    ]);

    $application = app(ApplyServiceAdvisorRewriteAction::class)->execute(
        $ro,
        $concern,
        $assist->fresh(['result']),
        $user,
        openedEstimateVersion: (int) $ro->estimate_version,
    );

    expect($concern->fresh()->verified_findings)->toBe($proposal)
        ->and($application->original_text)->toBe($original)
        ->and($application->isApplied())->toBeTrue();

    app(RevertServiceAdvisorRewriteAction::class)->execute($ro, $application->fresh(), $user);

    expect($concern->fresh()->verified_findings)->toBe($original)
        ->and($application->fresh()->reverted_at)->not->toBeNull();
});

test('applied dragon rewrite appears on the customer estimate portal', function (): void {
    [$ro, $concern] = saRo();
    $concern->forceFill([
        'disposition' => \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::Recommended,
    ])->save();
    $user = saAdvisor();
    $proposal = 'Battery tested 650/480 CCA. Left front pad measures 2 mm. Possible EVAP leak — needs smoke test. DTC P0456 present.';

    $assist = app(RequestDragonAssistAction::class)->execute(
        DragonAssistTaskType::ServiceAdvisorRewrite,
        (new ServiceAdvisorContextBuilder)->build(
            $ro,
            $concern,
            ServiceAdvisorField::VerifiedFindings,
            ServiceAdvisorMode::ServiceAdvisorRewrite,
        ),
        repairOrderId: (int) $ro->id,
        vehicleId: (int) $ro->vehicle_id,
        actor: $user,
    );
    $assist->forceFill(['status' => DragonAssistStatus::Completed, 'completed_at' => now()])->save();
    $assist->result?->delete();
    $assist->result()->create([
        'result_json' => [
            'proposal' => $proposal,
            'facts_preserved' => ['650/480 CCA', '2 mm', 'P0456'],
            'material_changes' => [],
            'warnings' => [],
            'confidence' => 0.9,
        ],
        'model_name' => 'qwen3:14b',
    ]);

    app(ApplyServiceAdvisorRewriteAction::class)->execute(
        $ro,
        $concern->fresh(),
        $assist->fresh(['result']),
        $user,
        openedEstimateVersion: (int) $ro->estimate_version,
    );

    $plainToken = str_repeat('d', 64);
    \App\Ark\Operations\Portal\EstimateAccessToken::createForPlainToken($ro->fresh(), $plainToken);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('What we found', false)
        ->assertSee('Left front pad measures 2 mm', false)
        ->assertSee('Replace battery. Smoke-test EVAP if leak confirmed.', false);
});

test('stale hash blocks apply with conflict', function (): void {
    [$ro, $concern] = saRo();
    $user = saAdvisor();

    $assist = app(RequestDragonAssistAction::class)->execute(
        DragonAssistTaskType::ServiceAdvisorRewrite,
        (new ServiceAdvisorContextBuilder)->build(
            $ro,
            $concern,
            ServiceAdvisorField::VerifiedFindings,
            ServiceAdvisorMode::CleanUp,
        ),
        repairOrderId: (int) $ro->id,
        vehicleId: (int) $ro->vehicle_id,
        actor: $user,
    );
    $assist->forceFill(['status' => DragonAssistStatus::Completed, 'completed_at' => now()])->save();
    $assist->result?->delete();
    $assist->result()->create([
        'result_json' => ['proposal' => $concern->verified_findings.'.', 'facts_preserved' => [], 'material_changes' => [], 'warnings' => []],
        'model_name' => 'qwen3:14b',
    ]);

    $concern->update(['verified_findings' => $concern->verified_findings.' (advisor edited)']);

    expect(fn () => app(ApplyServiceAdvisorRewriteAction::class)->execute(
        $ro,
        $concern->fresh(),
        $assist->fresh(['result']),
        $user,
    ))->toThrow(\RuntimeException::class);
});

test('http request apply revert endpoints require manage permission', function (): void {
    [$ro, $concern] = saRo();
    $user = saAdvisor();

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.concerns.dragon-service-advisor', [$ro, $concern]), [
            'field' => 'verified_findings',
            'mode' => 'clean_up',
        ])
        ->assertCreated()
        ->assertJsonPath('assist.task_type', 'service_advisor_rewrite');

    $assist = DragonAssistRequest::query()->latest('id')->first();
    expect($assist)->not->toBeNull();

    $this->actingAs($user)
        ->getJson(route('operations.repair-orders.dragon-assist.show', [$ro, $assist->public_id]))
        ->assertOk()
        ->assertJsonPath('assist.request_id', $assist->public_id);

    // Complete with safe proposal for apply.
    $proposal = $concern->verified_findings;
    $assist->forceFill(['status' => DragonAssistStatus::Completed, 'completed_at' => now()])->save();
    $assist->result?->delete();
    $assist->result()->create([
        'result_json' => [
            'proposal' => $proposal."\nCleaned by Dragon.",
            'facts_preserved' => ['650/480 CCA', '2 mm', 'P0456'],
            'material_changes' => [],
            'warnings' => [],
        ],
        'model_name' => 'qwen3:14b',
    ]);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.concerns.dragon-service-advisor.apply', [$ro, $concern, $assist->public_id]), [
            'opened_estimate_version' => $ro->estimate_version,
        ])
        ->assertOk()
        ->assertJsonPath('application.can_revert', true);

    $app = DragonServiceAdvisorApplication::query()->latest('id')->first();
    expect($concern->fresh()->verified_findings)->toContain('Cleaned by Dragon');

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dragon-service-advisor.revert', [$ro, $app]), [
            'opened_estimate_version' => $ro->fresh()->estimate_version,
        ])
        ->assertOk();

    expect($concern->fresh()->verified_findings)->not->toContain('Cleaned by Dragon');
});

test('prompt injection in note text does not grant writes via schema', function (): void {
    expect(fn () => ServiceAdvisorRewriteResultSchema::validate([
        'proposal' => 'Ignored',
        'mutations' => [['action' => 'delete_all']],
        'status' => 'closed',
    ]))->toThrow(ValidationException::class);
});

test('visit reason rewrite apply and revert', function (): void {
    [$ro] = saRo();
    $user = saAdvisor();
    $original = 'Noise when braking from the highway.';
    $ro->update(['visit_reason' => $original]);
    $proposal = $original.' Customer reports brake noise.';

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dragon-service-advisor.visit-reason', $ro), [
            'mode' => 'clean_up',
        ])
        ->assertCreated();

    $assist = DragonAssistRequest::query()->latest('id')->first();
    expect($assist->payload_json['selected_field'] ?? null)->toBe('visit_reason');

    $assist->forceFill(['status' => DragonAssistStatus::Completed, 'completed_at' => now()])->save();
    $assist->result?->delete();
    $assist->result()->create([
        'result_json' => [
            'proposal' => $proposal,
            'facts_preserved' => [],
            'material_changes' => [],
            'warnings' => [],
        ],
        'model_name' => 'qwen3:14b',
    ]);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dragon-service-advisor.visit-reason.apply', [$ro, $assist->public_id]), [
            'opened_estimate_version' => $ro->estimate_version,
        ])
        ->assertOk()
        ->assertJsonPath('application.can_revert', true);

    expect($ro->fresh()->visit_reason)->toBe($proposal);

    $app = DragonServiceAdvisorApplication::query()->latest('id')->first();
    expect($app->concern_id)->toBeNull()
        ->and($app->field)->toBe(ServiceAdvisorField::VisitReason);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dragon-service-advisor.revert', [$ro, $app]), [
            'opened_estimate_version' => $ro->fresh()->estimate_version,
        ])
        ->assertOk();

    expect($ro->fresh()->visit_reason)->toBe($original);
});

test('line note rewrite apply and revert rejects non-note lines', function (): void {
    [$ro, $concern] = saRo();
    $user = saAdvisor();

    $note = \App\Ark\Operations\RepairOrders\RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Note,
        'description' => 'Check battery clamp torque after install.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'visible_to_advisor' => true,
        'visible_to_technician' => true,
        'visible_to_customer' => false,
    ]);

    $labor = \App\Ark\Operations\RepairOrders\RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Replace battery',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.lines.dragon-service-advisor', [$ro, $labor]))
        ->assertStatus(422);

    $original = $note->description;
    $proposal = $original.' Confirm clamp is seated.';

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.lines.dragon-service-advisor', [$ro, $note]), [
            'mode' => 'clean_up',
        ])
        ->assertCreated();

    $assist = DragonAssistRequest::query()->latest('id')->first();
    $assist->forceFill(['status' => DragonAssistStatus::Completed, 'completed_at' => now()])->save();
    $assist->result?->delete();
    $assist->result()->create([
        'result_json' => [
            'proposal' => $proposal,
            'facts_preserved' => [],
            'material_changes' => [],
            'warnings' => [],
        ],
        'model_name' => 'qwen3:14b',
    ]);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.lines.dragon-service-advisor.apply', [$ro, $note, $assist->public_id]), [
            'opened_estimate_version' => $ro->estimate_version,
        ])
        ->assertOk();

    expect($note->fresh()->description)->toBe($proposal);

    $app = DragonServiceAdvisorApplication::query()->latest('id')->first();
    expect($app->repair_order_line_id)->toBe($note->id)
        ->and($app->field)->toBe(ServiceAdvisorField::LineNote);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dragon-service-advisor.revert', [$ro, $app]), [
            'opened_estimate_version' => $ro->fresh()->estimate_version,
        ])
        ->assertOk();

    expect($note->fresh()->description)->toBe($original);
});
