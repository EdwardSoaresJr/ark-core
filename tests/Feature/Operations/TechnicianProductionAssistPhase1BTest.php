<?php

use App\Ark\Operations\Labor\TechnicianCompensableTimeEntry;
use App\Ark\Operations\Labor\TechnicianCompensationAgreement;
use App\Ark\Operations\Labor\TechnicianFlagRecognition;
use App\Ark\Operations\Labor\TechnicianLaborPayBasis;
use App\Ark\Operations\Labor\TechnicianProductionAssistProjection;
use App\Ark\Operations\Labor\UpsertTechnicianCompensableWeekAction;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    config(['technician_compensation.recognition_authority_starts_at' => '2026-07-27']);
});

test('daily compensable time persists independently by date and period totals derive from daily entries', function () {
    $technician = flagAssistTechnician();
    $actor = actingAsLearnCurrentStaff(ArkRole::Admin);

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-27' => 8.0,
        '2026-07-28' => 8.0,
        '2026-07-29' => 0,
        '2026-07-30' => 8.25,
        '2026-07-31' => 8.0,
    ], $actor);

    expect(TechnicianCompensableTimeEntry::query()->where('user_id', $technician->id)->count())->toBe(5)
        ->and(TechnicianCompensableTimeEntry::totalHoursForTechnicianInRange(
            $technician->id,
            Carbon::parse('2026-07-27'),
            Carbon::parse('2026-07-31'),
        ))->toBe(32.25);

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-29' => null,
    ], $actor);

    expect(TechnicianCompensableTimeEntry::query()->where('user_id', $technician->id)->whereDate('work_date', '2026-07-29')->exists())->toBeFalse()
        ->and(TechnicianCompensableTimeEntry::totalHoursForTechnicianInRange(
            $technician->id,
            Carbon::parse('2026-07-27'),
            Carbon::parse('2026-07-31'),
        ))->toBe(32.25);
});

test('recognition uses immutable 1A facts and recognized lines fall into correct periods', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');
    $advisor = actingAsLearnCurrentAdvisor();

    [$ro, $concern] = flagRecognitionFixture($technician, laborHours: 4.60);
    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$ro, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    $recognition = TechnicianFlagRecognition::query()->firstOrFail();
    $recognition->forceFill(['recognized_at' => Carbon::parse('2026-07-28 10:00:00')])->save();

    $week = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    $otherWeek = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-08-03'),
        Carbon::parse('2026-08-09'),
    )['detail'];

    expect($week['recognized_flag_hours'])->toBe(4.6)
        ->and($week['recognized_lines'])->toHaveCount(1)
        ->and($otherWeek['recognized_flag_hours'])->toBe(0.0)
        ->and($otherWeek['recognized_lines'])->toBe([]);
});

test('pending excludes recognized labor-line identities and reopen does not return them', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');
    $advisor = actingAsLearnCurrentAdvisor();

    [$ro, $concern, $laborLine] = flagRecognitionFixture($technician, laborHours: 3.00);

    $pendingBefore = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    expect($pendingBefore['pending_flag_hours'])->toBe(3.0)
        ->and(collect($pendingBefore['pending_lines'])->pluck('repair_order_line_id')->all())->toContain($laborLine->id);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$ro, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    TechnicianFlagRecognition::query()->firstOrFail()
        ->forceFill(['recognized_at' => Carbon::parse('2026-07-28 12:00:00')])
        ->save();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$ro, $concern]), [
            'production_status' => ScopeProductionStatus::InProgress->value,
        ])
        ->assertRedirect();

    $after = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    expect(collect($after['pending_lines'])->pluck('repair_order_line_id')->all())->not->toContain($laborLine->id)
        ->and($after['recognized_flag_hours'])->toBe(3.0);
});

test('newly added unrecognized labor appears pending', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');
    $advisor = actingAsLearnCurrentAdvisor();

    [$ro, $concern] = flagRecognitionFixture($technician, laborHours: 2.00);
    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$ro, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    TechnicianFlagRecognition::query()->firstOrFail()
        ->forceFill(['recognized_at' => Carbon::parse('2026-07-28 09:00:00')])
        ->save();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$ro, $concern]), [
            'production_status' => ScopeProductionStatus::InProgress->value,
        ])
        ->assertRedirect();

    $newLine = $ro->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Extra labor',
        'quantity' => '1.50',
        'labor_billed_hours' => '1.50',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 22500,
        'tax_cents' => 0,
        'total_cents' => 22500,
    ]);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    expect($detail['pending_flag_hours'])->toBe(1.5)
        ->and(collect($detail['pending_lines'])->pluck('repair_order_line_id')->all())->toContain($newLine->id);
});

test('unassigned pending is visible and not silently attributed', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');

    flagRecognitionFixture(technician: null, laborHours: 5.00);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    expect($detail['pending_flag_hours'])->toBe(0.0)
        ->and($detail['pending_lines'])->toBe([])
        ->and($detail['unassigned_pending_lines'])->not->toBeEmpty()
        ->and(collect($detail['unassigned_pending_lines'])->sum('flag_hours'))->toBe(5.0);
});

test('recognized efficiency and non-earned production-in-view ratios calculate correctly', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');
    $advisor = actingAsLearnCurrentAdvisor();

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-27' => 8,
        '2026-07-28' => 8,
        '2026-07-29' => 8,
        '2026-07-30' => 8,
        '2026-07-31' => 8,
    ]);

    [$roDone, $concernDone] = flagRecognitionFixture($technician, laborHours: 19.6);
    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$roDone, $concernDone]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();
    TechnicianFlagRecognition::query()->firstOrFail()
        ->forceFill(['recognized_at' => Carbon::parse('2026-07-29 15:00:00')])
        ->save();

    flagRecognitionFixture($technician, laborHours: 17.8);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    expect($detail['clock_hours'])->toBe(40.0)
        ->and($detail['recognized_flag_hours'])->toBe(19.6)
        ->and($detail['pending_flag_hours'])->toBe(17.8)
        ->and($detail['recognized_efficiency_percent'])->toBe(49.0)
        ->and($detail['production_in_view_percent'])->toBe(93.5)
        ->and($detail['calculation']['pending_not_in_floor'])->toBeTrue();
});

test('flag earnings use agreement at recognition date and floor uses each compensable date', function () {
    $technician = flagAssistTechnician();

    TechnicianCompensationAgreement::query()->create([
        'user_id' => $technician->id,
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 2500,
        'floor_rate_cents' => 1516,
        'effective_from' => Carbon::parse('2026-07-27 00:00:00'),
        'effective_to' => Carbon::parse('2026-07-29 00:00:00'),
    ]);
    TechnicianCompensationAgreement::query()->create([
        'user_id' => $technician->id,
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 3000,
        'floor_rate_cents' => 1600,
        'effective_from' => Carbon::parse('2026-07-29 00:00:00'),
        'effective_to' => null,
    ]);
    $technician->forceFill([
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 3000,
        'floor_rate_cents' => 1600,
    ])->save();

    $advisor = actingAsLearnCurrentAdvisor();

    [$roEarly, $cEarly] = flagRecognitionFixture($technician, laborHours: 2.0);
    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$roEarly, $cEarly]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();
    TechnicianFlagRecognition::query()->orderBy('id')->first()
        ->forceFill(['recognized_at' => Carbon::parse('2026-07-28 11:00:00')])
        ->save();

    [$roLate, $cLate] = flagRecognitionFixture($technician, laborHours: 2.0);
    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$roLate, $cLate]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();
    TechnicianFlagRecognition::query()->orderByDesc('id')->first()
        ->forceFill(['recognized_at' => Carbon::parse('2026-07-30 11:00:00')])
        ->save();

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-28' => 8.0,
        '2026-07-30' => 8.0,
    ]);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    // 2hr × $25 + 2hr × $30 = $50 + $60 = $110
    expect($detail['recognized_earnings_cents'])->toBe(11000)
        ->and(collect($detail['recognized_lines'])->pluck('flag_rate_cents')->all())->toBe([2500, 3000]);

    // Floor: 8 × $15.16 + 8 × $16.00 = 12128 + 12800 = 24928
    expect($detail['floor_requirement_cents'])->toBe(24928);

    $floorByDate = collect($detail['daily_time'])->keyBy('date');
    expect($floorByDate['2026-07-28']['floor_rate_cents'])->toBe(1516)
        ->and($floorByDate['2026-07-30']['floor_rate_cents'])->toBe(1600);
});

test('below-floor exposure calculates and pending cannot reduce it; above-floor exposure is zero', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');
    $advisor = actingAsLearnCurrentAdvisor();

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-27' => 8,
        '2026-07-28' => 8,
        '2026-07-29' => 8,
        '2026-07-30' => 8,
        '2026-07-31' => 8,
    ]);

    [$ro, $concern] = flagRecognitionFixture($technician, laborHours: 19.6);
    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$ro, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();
    TechnicianFlagRecognition::query()->firstOrFail()
        ->forceFill(['recognized_at' => Carbon::parse('2026-07-28 10:00:00')])
        ->save();

    flagRecognitionFixture($technician, laborHours: 17.8);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    // 19.6 × $25 = $490; floor 40 × $15.16 = $606.40; exposure $116.40; assist $606.40
    expect($detail['recognized_earnings_cents'])->toBe(49000)
        ->and($detail['floor_requirement_cents'])->toBe(60640)
        ->and($detail['floor_exposure_cents'])->toBe(11640)
        ->and($detail['base_compensation_assist_cents'])->toBe(60640)
        ->and($detail['pending_flag_hours'])->toBe(17.8)
        ->and($detail['pending_value_cents'])->toBe(44500);

    // Above floor: recognize enough that earnings exceed floor
    $tech2 = flagAssistTechnician('above-floor@ark.test');
    seedFlagAgreement($tech2, flagCents: 2500, floorCents: 1516, from: '2026-07-01');
    app(UpsertTechnicianCompensableWeekAction::class)->handle($tech2, [
        '2026-07-27' => 8,
    ]);
    [$ro2, $c2] = flagRecognitionFixture($tech2, laborHours: 10.0);
    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$ro2, $c2]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();
    TechnicianFlagRecognition::query()->where('technician_user_id', $tech2->id)->firstOrFail()
        ->forceFill(['recognized_at' => Carbon::parse('2026-07-27 16:00:00')])
        ->save();

    $above = TechnicianProductionAssistProjection::forTechnician(
        $tech2,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    // 10 × $25 = $250; floor 8 × $15.16 = $121.28; exposure 0; assist $250
    expect($above['floor_exposure_cents'])->toBe(0)
        ->and($above['base_compensation_assist_cents'])->toBe(25000);
});

test('zero recognized with clock time produces full floor exposure', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-27' => 8,
        '2026-07-28' => 8,
        '2026-07-29' => 8,
        '2026-07-30' => 8,
        '2026-07-31' => 8,
    ]);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    expect($detail['recognized_flag_hours'])->toBe(0.0)
        ->and($detail['recognized_earnings_cents'])->toBe(0)
        ->and($detail['floor_requirement_cents'])->toBe(60640)
        ->and($detail['floor_exposure_cents'])->toBe(60640)
        ->and($detail['base_compensation_assist_cents'])->toBe(60640);
});

test('daily and weekly overtime review warnings fire without calculating OT dollars', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-27' => 12.0,
        '2026-07-28' => 12.0,
        '2026-07-29' => 12.0,
        '2026-07-30' => 8.0,
    ]);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    expect($detail['clock_hours'])->toBe(44.0)
        ->and($detail['overtime_review_required'])->toBeTrue()
        ->and($detail['overtime_warning']['message'])->toContain('does not include overtime')
        ->and($detail)->not->toHaveKey('overtime_earnings_cents');
});

test('historical period before recognition authority is unknown not zero', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-01-01');

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-20' => 8,
        '2026-07-21' => 8,
        '2026-07-22' => 8,
        '2026-07-23' => 8,
        '2026-07-24' => 8,
    ]);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-20'),
        Carbon::parse('2026-07-26'),
    )['detail'];

    expect($detail['history_unavailable'])->toBeTrue()
        ->and($detail['recognized_flag_hours'])->toBeNull()
        ->and($detail['pending_flag_hours'])->toBeNull()
        ->and($detail['floor_exposure_cents'])->toBeNull()
        ->and($detail['base_compensation_assist_cents'])->toBeNull()
        ->and($detail['history_unavailable_reason'])->toContain('Production history unavailable');
});

test('show me reconciles recognized earnings floor and assist exactly', function () {
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');
    $advisor = actingAsLearnCurrentAdvisor();

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-27' => 8,
        '2026-07-28' => 8,
        '2026-07-29' => 8,
        '2026-07-30' => 8,
        '2026-07-31' => 8,
    ]);

    [$ro, $concern] = flagRecognitionFixture($technician, laborHours: 19.6);
    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$ro, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();
    TechnicianFlagRecognition::query()->firstOrFail()
        ->forceFill(['recognized_at' => Carbon::parse('2026-07-28 10:00:00')])
        ->save();

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    $lineEarningsSum = (int) collect($detail['recognized_lines'])->sum('earnings_cents');
    $lineHoursSum = round((float) collect($detail['recognized_lines'])->sum('flag_hours'), 2);
    $dayFloorSum = (int) collect($detail['daily_time'])->sum(fn ($d) => $d['floor_amount_cents'] ?? 0);

    expect($lineHoursSum)->toBe($detail['recognized_flag_hours'])
        ->and($lineEarningsSum)->toBe($detail['recognized_earnings_cents'])
        ->and($dayFloorSum)->toBe($detail['floor_requirement_cents'])
        ->and($detail['calculation']['recognized_flag_earnings_cents'])->toBe($detail['recognized_earnings_cents'])
        ->and($detail['calculation']['floor_exposure_cents'])->toBe(
            max(0, $detail['floor_requirement_cents'] - $detail['recognized_earnings_cents']),
        )
        ->and($detail['calculation']['base_compensation_assist_cents'])->toBe(
            $detail['recognized_earnings_cents'] + $detail['floor_exposure_cents'],
        );
});

test('hourly technicians are excluded from flag floor assist and labor_cost_cents stays untouched', function () {
    $hourly = User::factory()->create([
        'name' => 'Hourly Tech',
        'email' => 'hourly-assist@ark.test',
        'labor_pay_basis' => TechnicianLaborPayBasis::Hourly->value,
        'flag_rate_cents' => null,
        'floor_rate_cents' => null,
        'labor_cost_cents' => 3200,
    ])->assignRole(ArkRole::Technician->value);

    $result = TechnicianProductionAssistProjection::forTechnician(
        $hourly,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    );

    expect($result['applies'])->toBeFalse()
        ->and($hourly->fresh()->labor_cost_cents)->toBe(3200);

    $flag = flagAssistTechnician();
    seedFlagAgreement($flag, flagCents: 2500, floorCents: 1516, from: '2026-07-01');
    $costBefore = $flag->labor_cost_cents;

    TechnicianProductionAssistProjection::forTechnician(
        $flag,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    );

    expect($flag->fresh()->labor_cost_cents)->toBe($costBefore);
});

test('owner can enter week hours and open shop technician production index', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $technician = flagAssistTechnician();
    seedFlagAgreement($technician, flagCents: 2500, floorCents: 1516, from: '2026-07-01');

    $this->actingAs($admin)
        ->post(route('operations.owner.technician-production.time', $technician), [
            'from' => '2026-07-27',
            'to' => '2026-08-02',
            'hours' => [
                '2026-07-27' => '8',
                '2026-07-28' => '8',
                '2026-07-29' => '8',
                '2026-07-30' => '8',
                '2026-07-31' => '8',
            ],
        ])
        ->assertRedirect(route('operations.owner.technician-production.show', [
            'user' => $technician,
            'from' => '2026-07-27',
            'to' => '2026-08-02',
        ]));

    expect(TechnicianCompensableTimeEntry::totalHoursForTechnicianInRange(
        $technician->id,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    ))->toBe(40.0);

    $this->actingAs($admin)
        ->get(route('operations.owner.technician-production.index', [
            'from' => '2026-07-27',
            'to' => '2026-08-02',
        ]))
        ->assertOk()
        ->assertSee($technician->name)
        ->assertSee('Why is this technician');

    $this->actingAs($admin)
        ->get(route('operations.owner.technician-production.show', [
            'user' => $technician,
            'from' => '2026-07-27',
            'to' => '2026-08-02',
        ]))
        ->assertOk()
        ->assertSee('Base compensation assist')
        ->assertSee('Show me');
});

function flagAssistTechnician(string $email = 'landon-assist@ark.test'): User
{
    return User::factory()->create([
        'name' => 'Landon Carter',
        'email' => $email,
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 2500,
        'floor_rate_cents' => 1516,
        'labor_cost_cents' => 8760,
    ])->assignRole(ArkRole::Technician->value);
}

function seedFlagAgreement(User $technician, int $flagCents, int $floorCents, string $from): void
{
    TechnicianCompensationAgreement::query()->create([
        'user_id' => $technician->id,
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => $flagCents,
        'floor_rate_cents' => $floorCents,
        'effective_from' => Carbon::parse($from)->startOfDay(),
        'effective_to' => null,
    ]);
}
