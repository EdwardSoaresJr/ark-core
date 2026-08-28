<?php

use App\Ark\Operations\Labor\ClockInTechnicianAction;
use App\Ark\Operations\Labor\ClockOutTechnicianAction;
use App\Ark\Operations\Labor\CorrectTechnicianTimeSessionAction;
use App\Ark\Operations\Labor\DeleteTechnicianTimeSessionAction;
use App\Ark\Operations\Labor\EnsureAutoClockSessionsAction;
use App\Ark\Operations\Labor\MarkOvernightOpenSessionsAction;
use App\Ark\Operations\Labor\RecomputeTechnicianCompensableDayAction;
use App\Ark\Operations\Labor\TechnicianCompensableTimeEntry;
use App\Ark\Operations\Labor\TechnicianCompensableTimeSource;
use App\Ark\Operations\Labor\TechnicianCompensationAgreement;
use App\Ark\Operations\Labor\TechnicianLaborPayBasis;
use App\Ark\Operations\Labor\TechnicianProductionAssistProjection;
use App\Ark\Operations\Labor\TechnicianTimeSession;
use App\Ark\Operations\Labor\TechnicianTimeSessionCloseReason;
use App\Ark\Operations\Labor\TechnicianTimeSessionCorrection;
use App\Ark\Operations\Labor\TechnicianTimeSessionOrigin;
use App\Ark\Operations\Labor\TechnicianTimeSessionStatus;
use App\Ark\Operations\Labor\UpdateTechnicianAutoClockPolicyAction;
use App\Ark\Operations\Labor\UpsertTechnicianCompensableWeekAction;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['technician_compensation.recognition_authority_starts_at' => '2026-07-27']);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('clock in and out derives eight punch derived compensable hours', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    app(ClockInTechnicianAction::class)->handle($technician, $technician);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());
    app(ClockOutTechnicianAction::class)->handle($technician, $technician);

    $entry = TechnicianCompensableTimeEntry::query()
        ->where('user_id', $technician->id)
        ->whereDate('work_date', '2026-07-28')
        ->first();

    expect($entry)->not->toBeNull()
        ->and((float) $entry->compensable_hours)->toBe(8.0)
        ->and($entry->source)->toBe(TechnicianCompensableTimeSource::PunchDerived->value)
        ->and($entry->manual_locked)->toBeFalse();
});

test('duplicate open punch is rejected', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    app(ClockInTechnicianAction::class)->handle($technician, $technician);

    expect(fn () => app(ClockInTechnicianAction::class)->handle($technician, $technician))
        ->toThrow(RuntimeException::class, 'An open time session already exists.');
});

test('overnight punch splits two hours per shop calendar day', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-27 22:00')->utc());

    $technician = timeClockTechnician();
    app(ClockInTechnicianAction::class)->handle($technician, $technician);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 02:00')->utc());
    app(ClockOutTechnicianAction::class)->handle($technician, $technician);

    $dayOne = TechnicianCompensableTimeEntry::query()
        ->where('user_id', $technician->id)
        ->whereDate('work_date', '2026-07-27')
        ->first();
    $dayTwo = TechnicianCompensableTimeEntry::query()
        ->where('user_id', $technician->id)
        ->whereDate('work_date', '2026-07-28')
        ->first();

    expect((float) $dayOne->compensable_hours)->toBe(2.0)
        ->and((float) $dayTwo->compensable_hours)->toBe(2.0);
});

test('open past midnight needs resolution and does not invent prior day totals', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-27 17:00')->utc());

    $technician = timeClockTechnician();
    app(ClockInTechnicianAction::class)->handle($technician, $technician);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());
    app(MarkOvernightOpenSessionsAction::class)->handle($technician);

    $session = TechnicianTimeSession::openForTechnician($technician->id);
    expect($session)->not->toBeNull()
        ->and($session->status)->toBe(TechnicianTimeSessionStatus::NeedsResolution->value);

    app(RecomputeTechnicianCompensableDayAction::class)->recomputeOne($technician, '2026-07-27');

    expect(
        TechnicianCompensableTimeEntry::query()
            ->where('user_id', $technician->id)
            ->whereDate('work_date', '2026-07-27')
            ->exists()
    )->toBeFalse();

    $todayHours = app(RecomputeTechnicianCompensableDayAction::class)->hoursForWorkDate($technician, '2026-07-28');
    expect($todayHours)->toBe(8.0);
});

test('manual locked day is not overwritten by punch recompute', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    app(UpsertTechnicianCompensableWeekAction::class)->handle($technician, [
        '2026-07-28' => 7.5,
    ], $admin);

    app(ClockInTechnicianAction::class)->handle($technician, $technician);
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());
    app(ClockOutTechnicianAction::class)->handle($technician, $technician);

    $entry = TechnicianCompensableTimeEntry::query()
        ->where('user_id', $technician->id)
        ->whereDate('work_date', '2026-07-28')
        ->firstOrFail();

    expect((float) $entry->compensable_hours)->toBe(7.5)
        ->and($entry->source)->toBe(TechnicianCompensableTimeSource::ManualOverride->value)
        ->and($entry->manual_locked)->toBeTrue();
});

test('correction writes audit row and updates derived hours', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    app(ClockInTechnicianAction::class)->handle($technician, $technician);
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());
    $session = app(ClockOutTechnicianAction::class)->handle($technician, $technician);

    app(CorrectTechnicianTimeSessionAction::class)->handle(
        $session,
        '2026-07-28 07:00',
        '2026-07-28 15:00',
        'Advisor forgot early punch.',
        $admin,
    );

    $correction = TechnicianTimeSessionCorrection::query()->firstOrFail();

    expect($correction->field)->toBe('clocked_in_at')
        ->and($correction->reason)->toBe('Advisor forgot early punch.')
        ->and($correction->corrected_by_user_id)->toBe($admin->id);

    $entry = TechnicianCompensableTimeEntry::query()
        ->where('user_id', $technician->id)
        ->whereDate('work_date', '2026-07-28')
        ->firstOrFail();

    expect((float) $entry->compensable_hours)->toBe(8.0)
        ->and($entry->source)->toBe(TechnicianCompensableTimeSource::PunchDerived->value);
});

test('delete voids punch, writes audit, and clears punch derived hours', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    app(ClockInTechnicianAction::class)->handle($technician, $technician);
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());
    $session = app(ClockOutTechnicianAction::class)->handle($technician, $technician);

    expect(
        TechnicianCompensableTimeEntry::query()
            ->where('user_id', $technician->id)
            ->whereDate('work_date', '2026-07-28')
            ->exists()
    )->toBeTrue();

    app(DeleteTechnicianTimeSessionAction::class)->handle(
        $session,
        'Accidental test punch.',
        $admin,
    );

    $session->refresh();
    $correction = TechnicianTimeSessionCorrection::query()
        ->where('technician_time_session_id', $session->id)
        ->where('field', 'deleted')
        ->firstOrFail();

    expect($session->status)->toBe(TechnicianTimeSessionStatus::Deleted->value)
        ->and($correction->reason)->toBe('Accidental test punch.')
        ->and($correction->corrected_by_user_id)->toBe($admin->id)
        ->and(
            TechnicianCompensableTimeEntry::query()
                ->where('user_id', $technician->id)
                ->whereDate('work_date', '2026-07-28')
                ->exists()
        )->toBeFalse();
});

test('delete open punch frees clock in and stops accruing hours', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $session = app(ClockInTechnicianAction::class)->handle($technician, $technician);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 10:00')->utc());

    app(DeleteTechnicianTimeSessionAction::class)->handle(
        $session,
        'Wrong clock in.',
        $admin,
    );

    expect(TechnicianTimeSession::openForTechnician($technician->id))->toBeNull();

    $hours = app(RecomputeTechnicianCompensableDayAction::class)->hoursForWorkDate($technician, '2026-07-28');
    expect($hours)->toBe(0.0);

    $next = app(ClockInTechnicianAction::class)->handle($technician, $technician);
    expect($next->id)->not->toBe($session->id)
        ->and($next->status)->toBe(TechnicianTimeSessionStatus::Open->value);
});

test('delete punch http doorway requires owner and reason', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician('landon-delete@ark.test');
    completeRequiredLearnFor($technician);
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    app(ClockInTechnicianAction::class)->handle($technician, $technician);
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:05')->utc());
    $session = app(ClockOutTechnicianAction::class)->handle($technician, $technician);

    $this->actingAs($technician)
        ->post(route('operations.time-clock.delete', $session), ['reason' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('operations.time-clock.delete', $session), [])
        ->assertSessionHasErrors('reason');

    $this->actingAs($admin)
        ->post(route('operations.time-clock.delete', $session), [
            'reason' => 'Test punch during ship verify.',
        ])
        ->assertRedirect(route('operations.time-clock.staff', $technician));

    expect($session->fresh()->status)->toBe(TechnicianTimeSessionStatus::Deleted->value);
});

test('phase 1b clock hours matches punch derived daily rows', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    seedTimeClockFlagAgreement($technician);

    app(ClockInTechnicianAction::class)->handle($technician, $technician);
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());
    app(ClockOutTechnicianAction::class)->handle($technician, $technician);

    $detail = TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    )['detail'];

    expect($detail['clock_hours'])->toBe(8.0);

    $costBefore = $technician->fresh()->labor_cost_cents;
    TechnicianProductionAssistProjection::forTechnician(
        $technician,
        Carbon::parse('2026-07-27'),
        Carbon::parse('2026-08-02'),
    );
    expect($technician->fresh()->labor_cost_cents)->toBe($costBefore);
});

test('time clock http doorway supports self punch and owner staff view', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    completeRequiredLearnFor($technician);
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    seedTimeClockFlagAgreement($technician);

    $this->actingAs($technician)
        ->get(route('operations.time-clock.index'))
        ->assertOk()
        ->assertSee('Clock In');

    $this->actingAs($technician)
        ->post(route('operations.time-clock.in'))
        ->assertRedirect(route('operations.time-clock.index'));

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());

    $this->actingAs($technician)
        ->post(route('operations.time-clock.out'))
        ->assertRedirect(route('operations.time-clock.index'));

    $this->actingAs($admin)
        ->get(route('operations.time-clock.staff', $technician))
        ->assertOk()
        ->assertSee('Punch history')
        ->assertSee('Tech production');
});

test('admins and advisors can punch a technician in and out', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician('landon-proxy@ark.test');
    completeRequiredLearnFor($technician);
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);

    $this->actingAs($advisor)
        ->get(route('operations.time-clock.index'))
        ->assertOk()
        ->assertSee('Staff punches')
        ->assertSee($technician->name);

    $this->actingAs($advisor)
        ->get(route('operations.time-clock.staff', $technician))
        ->assertOk()
        ->assertSee('Clock In')
        ->assertDontSee('Tech production');

    $this->actingAs($advisor)
        ->post(route('operations.time-clock.staff.in', $technician))
        ->assertRedirect(route('operations.time-clock.staff', $technician));

    $session = TechnicianTimeSession::openForTechnician($technician->id);
    expect($session)->not->toBeNull()
        ->and($session->clocked_in_by_user_id)->toBe($advisor->id);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($admin)
        ->post(route('operations.time-clock.staff.out', $technician))
        ->assertRedirect(route('operations.time-clock.staff', $technician));

    $session->refresh();
    expect($session->clocked_out_at)->not->toBeNull()
        ->and($session->clocked_out_by_user_id)->toBe($admin->id)
        ->and($session->status)->toBe(TechnicianTimeSessionStatus::Closed->value);

    $entry = TechnicianCompensableTimeEntry::query()
        ->where('user_id', $technician->id)
        ->whereDate('work_date', '2026-07-28')
        ->firstOrFail();
    expect((float) $entry->compensable_hours)->toBe(8.0)
        ->and($entry->source)->toBe(TechnicianCompensableTimeSource::PunchDerived->value);

    $this->actingAs($advisor)
        ->post(route('operations.time-clock.delete', $session), ['reason' => 'Nope'])
        ->assertForbidden();
});

test('out for lunch and back creates unpaid gap', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $technician = timeClockTechnician();
    app(ClockInTechnicianAction::class)->handle($technician, $technician);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 12:00')->utc());
    $lunchSession = app(ClockOutTechnicianAction::class)->handle(
        $technician,
        $technician,
        TechnicianTimeSessionCloseReason::Lunch,
    );

    expect($lunchSession->close_reason)->toBe(TechnicianTimeSessionCloseReason::Lunch->value);
    expect(TechnicianTimeSession::openForTechnician($technician->id))->toBeNull();

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 12:30')->utc());
    app(ClockInTechnicianAction::class)->handle($technician, $technician);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());
    app(ClockOutTechnicianAction::class)->handle($technician, $technician);

    $hours = app(RecomputeTechnicianCompensableDayAction::class)->hoursForWorkDate($technician, '2026-07-28');
    expect($hours)->toBe(7.5);
});

test('admin assigns auto clock day; it materializes at open and closes at close minus lunch', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 07:00')->utc());

    $technician = timeClockTechnician();
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    app(UpdateTechnicianAutoClockPolicyAction::class)->handle($technician, true, 30, $admin);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 10:00')->utc());
    app(EnsureAutoClockSessionsAction::class)->handle();

    $openSession = TechnicianTimeSession::openForTechnician($technician->id);
    expect($openSession)->not->toBeNull()
        ->and($openSession->origin)->toBe(TechnicianTimeSessionOrigin::Auto->value)
        ->and(ShopDisplayTimezone::present($openSession->clocked_in_at)->format('H:i'))->toBe('09:00');

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 18:00')->utc());
    app(EnsureAutoClockSessionsAction::class)->handle();

    $closed = $openSession->fresh();
    expect($closed->clocked_out_at)->not->toBeNull()
        ->and($closed->close_reason)->toBe(TechnicianTimeSessionCloseReason::EndOfDay->value)
        ->and(ShopDisplayTimezone::present($closed->clocked_out_at)->format('H:i'))->toBe('18:00');

    $hours = app(RecomputeTechnicianCompensableDayAction::class)->hoursForWorkDate($technician, '2026-07-28');
    expect($hours)->toBe(8.5);
});

test('auto lunch deduction is skipped when a real lunch punch exists', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 09:00')->utc());

    $technician = timeClockTechnician();
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    app(UpdateTechnicianAutoClockPolicyAction::class)->handle($technician, true, 30, $admin);
    app(EnsureAutoClockSessionsAction::class)->handle();

    expect(TechnicianTimeSession::openForTechnician($technician->id))->not->toBeNull();

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 12:00')->utc());
    app(ClockOutTechnicianAction::class)->handle($technician, $technician, TechnicianTimeSessionCloseReason::Lunch);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 12:30')->utc());
    app(ClockInTechnicianAction::class)->handle($technician, $technician);

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 18:00')->utc());
    app(EnsureAutoClockSessionsAction::class)->handle();

    $hours = app(RecomputeTechnicianCompensableDayAction::class)->hoursForWorkDate($technician, '2026-07-28');
    expect($hours)->toBe(8.5);
});

test('advisors can self punch', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 08:00')->utc());

    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);

    $this->actingAs($advisor)
        ->post(route('operations.time-clock.in'))
        ->assertRedirect(route('operations.time-clock.index'));

    expect(TechnicianTimeSession::openForTechnician($advisor->id))->not->toBeNull();

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-28 16:00')->utc());

    $this->actingAs($advisor)
        ->post(route('operations.time-clock.out'))
        ->assertRedirect(route('operations.time-clock.index'));

    $entry = TechnicianCompensableTimeEntry::query()
        ->where('user_id', $advisor->id)
        ->whereDate('work_date', '2026-07-28')
        ->firstOrFail();

    expect((float) $entry->compensable_hours)->toBe(8.0);
});

function timeClockTechnician(string $email = 'landon-clock@ark.test'): User
{
    return User::factory()->create([
        'name' => 'Landon Clock',
        'email' => $email,
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 2500,
        'floor_rate_cents' => 1516,
        'labor_cost_cents' => 8760,
    ])->assignRole(ArkRole::Technician->value);
}

function seedTimeClockFlagAgreement(User $technician): void
{
    TechnicianCompensationAgreement::query()->create([
        'user_id' => $technician->id,
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 2500,
        'floor_rate_cents' => 1516,
        'effective_from' => Carbon::parse('2026-07-01')->startOfDay(),
        'effective_to' => null,
    ]);
}
