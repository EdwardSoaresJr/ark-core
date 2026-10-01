<?php

use App\Ark\Dragon\Assist\DragonAssistProjection;
use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistStatus;
use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Dragon\Assist\RequestDragonAssistAction;
use App\Ark\Dragon\ReviewEstimateNotes\RequestReviewEstimateNotesAction;
use App\Ark\Dragon\ReviewEstimateNotes\ReviewEstimateNotesContextBuilder;
use App\Ark\Dragon\ReviewEstimateNotes\ReviewEstimateNotesResultSchema;
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

function renRo(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Review',
        'last_name' => 'Notes',
        'phone' => '5551112222',
        'email' => 'review-notes@example.com',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Noise when braking',
        'visit_reason' => 'Noise when braking',
        'estimate_version' => 1,
    ]);
    $ro->concerns()->create([
        'summary' => 'Brake noise',
        'customer_states' => 'Squeal when stopping.',
        'verified_findings' => 'LF pad 2 mm. RF 4 mm.',
        'recommendation' => '',
        'position' => 1,
    ]);
    $ro->concerns()->create([
        'summary' => 'Battery',
        'verified_findings' => '',
        'recommendation' => 'Replace battery',
        'position' => 2,
    ]);

    return $ro->fresh(['vehicle', 'concerns']);
}

function renAdvisor(): User
{
    $user = User::factory()->create(['name' => 'REN Advisor']);
    $user->assignRole(ArkRole::Advisor->value);

    return $user;
}

test('review estimate notes context excludes pii and includes concerns', function (): void {
    $ro = renRo();
    $payload = (new ReviewEstimateNotesContextBuilder)->build($ro);
    $encoded = strtolower(json_encode($payload) ?: '');

    expect($encoded)->not->toContain('"phone"')
        ->and($encoded)->not->toContain('"email"')
        ->and($payload['task'])->toBe('review_estimate_notes')
        ->and($payload['concerns'])->toHaveCount(2)
        ->and($payload['visit_reason'])->toBe('Noise when braking');
});

test('review estimate notes schema accepts proposals and still prohibits mutations', function (): void {
    $ok = ReviewEstimateNotesResultSchema::validate([
        'summary' => 'Two concerns need attention.',
        'gaps' => ['Battery missing findings'],
        'suggested_actions' => ['Add battery findings'],
        'confidence' => 0.7,
        'proposals' => [
            [
                'concern_id' => 12,
                'field' => 'verified_findings',
                'original_text' => 'LF pad 2 mm',
                'proposed_text' => 'LF pad measured 2 mm.',
                'reason' => 'Clarify measurement',
            ],
        ],
    ]);
    expect($ok['summary'])->toContain('Two concerns')
        ->and($ok['proposals'])->toHaveCount(1)
        ->and($ok['proposals'][0]['field'])->toBe('verified_findings');

    expect(fn () => ReviewEstimateNotesResultSchema::validate([
        'summary' => 'x',
        'mutations' => [['field' => 'recommendation']],
    ]))->toThrow(ValidationException::class);

    expect(fn () => ReviewEstimateNotesResultSchema::validate([
        'summary' => 'x',
        'rewrites' => [['field' => 'verified_findings', 'text' => 'changed']],
    ]))->toThrow(ValidationException::class);
});

test('review estimate notes request does not mutate concerns', function (): void {
    $ro = renRo();
    $before = $ro->concerns->map(fn ($c) => [
        'vf' => $c->verified_findings,
        'rec' => $c->recommendation,
    ])->all();

    $assist = app(RequestReviewEstimateNotesAction::class)->execute($ro, renAdvisor());

    expect($assist->task_type)->toBe(DragonAssistTaskType::ReviewEstimateNotes);
    $ro->load('concerns');
    expect($ro->concerns->map(fn ($c) => [
        'vf' => $c->verified_findings,
        'rec' => $c->recommendation,
    ])->all())->toBe($before);
});

test('completed review estimate notes enriches proposals without mutating narrative', function (): void {
    $ro = renRo();
    $brake = $ro->concerns()->where('summary', 'Brake noise')->first();
    $safeCustomerStates = 'Customer reports squeal when stopping.';
    $droppedFindings = 'Pads are worn.'; // drops 2 mm / 4 mm / LF / RF

    app(\App\Ark\Dragon\Agent\Providers\FakeDragonProvider::class)->structuredQueue = [[
        'summary' => 'Brake concern is stronger than battery.',
        'strengths' => ['Brake findings include measurements'],
        'gaps' => ['Battery missing verified findings', 'Brake missing recommendation'],
        'inconsistencies' => [],
        'customer_readiness' => 'Not ready until gaps are filled.',
        'suggested_actions' => ['Add battery findings'],
        'warnings' => [],
        'confidence' => 0.8,
        'proposals' => [
            [
                'concern_id' => $brake->id,
                'field' => 'customer_states',
                'original_text' => $brake->customer_states,
                'proposed_text' => $safeCustomerStates,
                'reason' => 'Clarify customer voice',
            ],
            [
                'concern_id' => $brake->id,
                'field' => 'verified_findings',
                'original_text' => $brake->verified_findings,
                'proposed_text' => $droppedFindings,
                'reason' => 'Would drop measurements',
            ],
        ],
    ]];

    $assist = app(RequestDragonAssistAction::class)->execute(
        DragonAssistTaskType::ReviewEstimateNotes,
        (new ReviewEstimateNotesContextBuilder)->build($ro),
        repairOrderId: (int) $ro->id,
        vehicleId: (int) $ro->vehicle_id,
    );

    $projection = DragonAssistProjection::fromRequest($assist->fresh(['result']));
    expect($projection['available'])->toBeTrue()
        ->and($projection['gaps'])->toContain('Battery missing verified findings')
        ->and($projection['task_type'])->toBe('review_estimate_notes')
        ->and($projection['proposals'])->toHaveCount(2);

    $byField = collect($projection['proposals'])->keyBy('field');
    expect($byField['customer_states']['applyable'])->toBeTrue()
        ->and($byField['customer_states']['original_hash'])->not->toBeEmpty()
        ->and($byField['verified_findings']['applyable'])->toBeFalse();

    // Completing critique must not rewrite narrative fields.
    $battery = $ro->concerns()->where('summary', 'Battery')->first();
    expect(trim((string) ($battery->verified_findings ?? '')))->toBe('')
        ->and($brake->fresh()->verified_findings)->toBe($brake->verified_findings);
});

test('apply review estimate notes proposal writes field and supports revert', function (): void {
    $ro = renRo();
    $brake = $ro->concerns()->where('summary', 'Brake noise')->first();
    $user = renAdvisor();
    $original = (string) $brake->verified_findings;
    $proposal = $original."\nClarified for presentation.";

    $assist = app(RequestDragonAssistAction::class)->execute(
        DragonAssistTaskType::ReviewEstimateNotes,
        (new ReviewEstimateNotesContextBuilder)->build($ro),
        repairOrderId: (int) $ro->id,
        vehicleId: (int) $ro->vehicle_id,
        actor: $user,
    );
    $assist->forceFill(['status' => DragonAssistStatus::Completed, 'completed_at' => now()])->save();
    $assist->result?->delete();
    $assist->result()->create([
        'result_json' => [
            'summary' => 'Ready with one proposal.',
            'strengths' => [],
            'gaps' => [],
            'inconsistencies' => [],
            'customer_readiness' => null,
            'suggested_actions' => [],
            'warnings' => [],
            'proposals' => [
                [
                    'concern_id' => $brake->id,
                    'field' => 'verified_findings',
                    'original_text' => $original,
                    'proposed_text' => $proposal,
                    'reason' => 'Clarify',
                    'original_hash' => \App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorContextBuilder::hashText($original),
                    'applyable' => true,
                    'rejected_reason' => null,
                ],
            ],
        ],
        'model_name' => 'qwen3:14b',
    ]);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.review-estimate-notes.apply', [$ro, $assist->public_id]), [
            'concern_id' => $brake->id,
            'field' => 'verified_findings',
            'opened_estimate_version' => $ro->estimate_version,
        ])
        ->assertOk()
        ->assertJsonPath('application.can_revert', true);

    expect($brake->fresh()->verified_findings)->toBe($proposal);

    $app = \App\Ark\Dragon\ServiceAdvisor\DragonServiceAdvisorApplication::query()->latest('id')->first();
    expect($app)->not->toBeNull()
        ->and($app->dragon_assist_request_id)->toBe($assist->id);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dragon-service-advisor.revert', [$ro, $app]), [
            'opened_estimate_version' => $ro->fresh()->estimate_version,
        ])
        ->assertOk();

    expect($brake->fresh()->verified_findings)->toBe($original);
});

test('http review estimate notes endpoint creates assist', function (): void {
    $ro = renRo();
    $user = renAdvisor();

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.review-estimate-notes', $ro))
        ->assertCreated()
        ->assertJsonPath('assist.task_type', 'review_estimate_notes');

    expect(DragonAssistRequest::query()->where('task_type', DragonAssistTaskType::ReviewEstimateNotes)->count())->toBe(1);
});

test('review estimate notes schema accepts visit_reason and line_note proposals', function (): void {
    $ok = ReviewEstimateNotesResultSchema::validate([
        'summary' => 'Visit reason and a note line can improve.',
        'proposals' => [
            [
                'field' => 'visit_reason',
                'original_text' => 'Noise when braking',
                'proposed_text' => 'Noise when braking (clarified).',
                'reason' => 'Tighten',
            ],
            [
                'line_id' => 99,
                'field' => 'line_note',
                'original_text' => 'Tech note',
                'proposed_text' => 'Tech note clarified.',
                'reason' => 'Tighten',
            ],
        ],
    ]);
    expect($ok['proposals'])->toHaveCount(2)
        ->and($ok['proposals'][0]['field'])->toBe('visit_reason')
        ->and($ok['proposals'][0]['concern_id'])->toBeNull()
        ->and($ok['proposals'][1]['field'])->toBe('line_note')
        ->and($ok['proposals'][1]['line_id'])->toBe(99);

    expect(fn () => ReviewEstimateNotesResultSchema::validate([
        'summary' => 'x',
        'proposals' => [
            ['field' => 'line_note', 'proposed_text' => 'Missing line id'],
        ],
    ]))->toThrow(ValidationException::class);

    expect(fn () => ReviewEstimateNotesResultSchema::validate([
        'summary' => 'x',
        'proposals' => [
            ['field' => 'verified_findings', 'proposed_text' => 'Missing concern id'],
        ],
    ]))->toThrow(ValidationException::class);
});

test('whole-ro review context includes note_lines and may propose visit reason', function (): void {
    $ro = renRo();
    $brake = $ro->concerns()->where('summary', 'Brake noise')->first();
    $ro->lines()->create([
        'repair_order_concern_id' => $brake->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Note,
        'description' => 'Road test confirmed LF squeal.',
        'quantity' => 1,
        'unit_price_cents' => 0,
    ]);

    $payload = (new ReviewEstimateNotesContextBuilder)->build($ro->fresh(['vehicle', 'concerns', 'lines']));
    expect($payload['note_lines'])->toHaveCount(1)
        ->and($payload['constraints']['may_propose_visit_reason'])->toBeTrue()
        ->and($payload['constraints']['may_propose_line_notes'])->toBeTrue();

    $scoped = (new ReviewEstimateNotesContextBuilder)->build($ro->fresh(['vehicle', 'concerns', 'lines']), $brake);
    expect($scoped['constraints']['may_propose_visit_reason'])->toBeFalse()
        ->and($scoped['note_lines'])->toHaveCount(1);
});

test('apply visit_reason and line_note proposals from whole-ro review', function (): void {
    $ro = renRo();
    $brake = $ro->concerns()->where('summary', 'Brake noise')->first();
    $line = $ro->lines()->create([
        'repair_order_concern_id' => $brake->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Note,
        'description' => 'Road test confirmed LF squeal.',
        'quantity' => 1,
        'unit_price_cents' => 0,
    ]);
    $user = renAdvisor();
    $visitOriginal = (string) $ro->visit_reason;
    $visitProposal = $visitOriginal.' Clarified for intake.';
    $lineOriginal = (string) $line->description;
    $lineProposal = $lineOriginal.' Clarified for presentation.';

    $assist = app(RequestDragonAssistAction::class)->execute(
        DragonAssistTaskType::ReviewEstimateNotes,
        (new ReviewEstimateNotesContextBuilder)->build($ro->fresh(['vehicle', 'concerns', 'lines'])),
        repairOrderId: (int) $ro->id,
        vehicleId: (int) $ro->vehicle_id,
        actor: $user,
    );
    $assist->forceFill(['status' => DragonAssistStatus::Completed, 'completed_at' => now()])->save();
    $assist->result?->delete();
    $assist->result()->create([
        'result_json' => [
            'summary' => 'Visit reason and note line proposals.',
            'strengths' => [],
            'gaps' => [],
            'inconsistencies' => [],
            'customer_readiness' => null,
            'suggested_actions' => [],
            'warnings' => [],
            'proposals' => [
                [
                    'concern_id' => null,
                    'line_id' => null,
                    'field' => 'visit_reason',
                    'original_text' => $visitOriginal,
                    'proposed_text' => $visitProposal,
                    'reason' => 'Tighten',
                    'original_hash' => \App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorContextBuilder::hashText($visitOriginal),
                    'applyable' => true,
                    'rejected_reason' => null,
                ],
                [
                    'concern_id' => $brake->id,
                    'line_id' => $line->id,
                    'field' => 'line_note',
                    'original_text' => $lineOriginal,
                    'proposed_text' => $lineProposal,
                    'reason' => 'Tighten',
                    'original_hash' => \App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorContextBuilder::hashText($lineOriginal),
                    'applyable' => true,
                    'rejected_reason' => null,
                ],
            ],
        ],
        'model_name' => 'qwen3:14b',
    ]);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.review-estimate-notes.apply', [$ro, $assist->public_id]), [
            'field' => 'visit_reason',
            'opened_estimate_version' => $ro->estimate_version,
        ])
        ->assertOk();

    expect($ro->fresh()->visit_reason)->toBe($visitProposal);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.review-estimate-notes.apply', [$ro, $assist->public_id]), [
            'field' => 'line_note',
            'line_id' => $line->id,
            'opened_estimate_version' => $ro->fresh()->estimate_version,
        ])
        ->assertOk();

    expect($line->fresh()->description)->toBe($lineProposal);
});

test('scoped review drops visit_reason proposals and out-of-scope line notes', function (): void {
    $ro = renRo();
    $brake = $ro->concerns()->where('summary', 'Brake noise')->first();
    $battery = $ro->concerns()->where('summary', 'Battery')->first();
    $brakeLine = $ro->lines()->create([
        'repair_order_concern_id' => $brake->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Note,
        'description' => 'Brake note.',
        'quantity' => 1,
        'unit_price_cents' => 0,
    ]);
    $batteryLine = $ro->lines()->create([
        'repair_order_concern_id' => $battery->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Note,
        'description' => 'Battery note.',
        'quantity' => 1,
        'unit_price_cents' => 0,
    ]);

    $assist = app(RequestDragonAssistAction::class)->execute(
        DragonAssistTaskType::ReviewEstimateNotes,
        (new ReviewEstimateNotesContextBuilder)->build($ro->fresh(['vehicle', 'concerns', 'lines']), $brake),
        repairOrderId: (int) $ro->id,
        vehicleId: (int) $ro->vehicle_id,
    );

    $result = [
        'summary' => 'Scoped.',
        'strengths' => [],
        'gaps' => [],
        'inconsistencies' => [],
        'customer_readiness' => null,
        'suggested_actions' => [],
        'warnings' => [],
        'proposals' => [
            [
                'field' => 'visit_reason',
                'original_text' => $ro->visit_reason,
                'proposed_text' => $ro->visit_reason.' x',
                'reason' => 'Should drop',
            ],
            [
                'line_id' => $brakeLine->id,
                'field' => 'line_note',
                'original_text' => 'Brake note.',
                'proposed_text' => 'Brake note. Clarified.',
                'reason' => 'Keep',
            ],
            [
                'line_id' => $batteryLine->id,
                'field' => 'line_note',
                'original_text' => 'Battery note.',
                'proposed_text' => 'Battery note. Clarified.',
                'reason' => 'Wrong concern',
            ],
        ],
    ];

    $enriched = (new \App\Ark\Dragon\ReviewEstimateNotes\EnrichReviewEstimateNotesProposals)->enrich($assist, $result);
    expect($enriched['proposals'])->toHaveCount(2);
    $byLine = collect($enriched['proposals'])->keyBy('line_id');
    expect($byLine[$brakeLine->id]['applyable'])->toBeTrue()
        ->and($byLine[$batteryLine->id]['applyable'])->toBeFalse();
});

test('scoped review estimate notes only includes one concern and filters proposals', function (): void {
    $ro = renRo();
    $brake = $ro->concerns()->where('summary', 'Brake noise')->first();
    $battery = $ro->concerns()->where('summary', 'Battery')->first();
    $user = renAdvisor();

    $payload = (new ReviewEstimateNotesContextBuilder)->build($ro, $brake);
    expect($payload['concerns'])->toHaveCount(1)
        ->and($payload['scope']['concern_id'])->toBe((int) $brake->id);

    $this->actingAs($user)
        ->postJson(route('operations.repair-orders.review-estimate-notes', $ro), [
            'concern_id' => $brake->id,
        ])
        ->assertCreated();

    $assist = DragonAssistRequest::query()->latest('id')->first();
    expect($assist->payload_json['scope']['concern_id'] ?? null)->toBe($brake->id);

    $assist->forceFill(['status' => DragonAssistStatus::Completed, 'completed_at' => now()])->save();
    // Simulate completion enrich path: mark completed via bridge-style enricher
    $result = [
        'summary' => 'Brake only.',
        'strengths' => [],
        'gaps' => [],
        'inconsistencies' => [],
        'customer_readiness' => null,
        'suggested_actions' => [],
        'warnings' => [],
        'proposals' => [
            [
                'concern_id' => $brake->id,
                'field' => 'customer_states',
                'original_text' => $brake->customer_states,
                'proposed_text' => 'Customer reports squeal when stopping.',
                'reason' => 'Clarify',
            ],
            [
                'concern_id' => $battery->id,
                'field' => 'recommendation',
                'original_text' => $battery->recommendation,
                'proposed_text' => 'Replace battery soon.',
                'reason' => 'Wrong concern',
            ],
        ],
    ];
    $enriched = (new \App\Ark\Dragon\ReviewEstimateNotes\EnrichReviewEstimateNotesProposals)->enrich($assist, $result);
    expect($enriched['proposals'])->toHaveCount(1)
        ->and($enriched['proposals'][0]['concern_id'])->toBe($brake->id);
});
