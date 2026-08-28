<?php

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Labor\FlagRecognitionPolicy;
use App\Ark\Operations\Labor\TechnicianCompensationAgreement;
use App\Ark\Operations\Labor\TechnicianFlagRecognition;
use App\Ark\Operations\Labor\TechnicianFlagRecognitionLine;
use App\Ark\Operations\Labor\TechnicianLaborPayBasis;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('first completed transition recognizes flag hours for assigned technician', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $concern, $laborLine] = flagRecognitionFixture($technician, laborHours: 4.60);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    $recognition = TechnicianFlagRecognition::query()->first();

    expect($recognition)->not->toBeNull()
        ->and($recognition->technician_user_id)->toBe($technician->id)
        ->and((float) $recognition->flag_hours_total)->toBe(4.60)
        ->and($recognition->recognition_policy)->toBe(FlagRecognitionPolicy::KEY)
        ->and($recognition->recognition_policy_version)->toBe(FlagRecognitionPolicy::VERSION)
        ->and($recognition->technician_attribution_source)->toBe(FlagRecognitionPolicy::TECHNICIAN_ATTRIBUTION_RO_ASSIGNEE)
        ->and($recognition->actor_user_id)->toBe($advisor->id)
        ->and($recognition->source_operational_event_id)->not->toBeNull();

    expect($recognition->lines)->toHaveCount(1)
        ->and($recognition->lines->first()->repair_order_line_id)->toBe($laborLine->id)
        ->and((float) $recognition->lines->first()->flag_hours)->toBe(4.60)
        ->and($recognition->lines->sum(fn ($line) => (float) $line->flag_hours))->toBe(4.60);

    expect(OperationalEvent::query()->where('event_name', OperationalEventName::FlagProductionRecognized->value)->exists())->toBeTrue();
});

test('recognition snapshots technician and survives later ro reassignment', function () {
    $landon = actingAsLearnCurrentStaff(ArkRole::Technician);
    $other = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $concern] = flagRecognitionFixture($landon, laborHours: 2.00);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    $recognitionId = TechnicianFlagRecognition::query()->value('id');
    $repairOrder->forceFill(['assigned_technician_id' => $other->id])->save();

    expect(TechnicianFlagRecognition::query()->find($recognitionId)->technician_user_id)->toBe($landon->id);
});

test('labor hour edits after recognition cannot rewrite recognized hours', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $concern, $laborLine] = flagRecognitionFixture($technician, laborHours: 3.00);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    $laborLine->update(['quantity' => '9.00', 'labor_billed_hours' => '9.00']);

    $recognition = TechnicianFlagRecognition::query()->first();

    expect((float) $recognition->flag_hours_total)->toBe(3.00)
        ->and((float) $recognition->lines->first()->flag_hours)->toBe(3.00);
});

test('completed reopen completed does not duplicate previously recognized production', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $concern, $laborLine] = flagRecognitionFixture($technician, laborHours: 4.60);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::InProgress->value,
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    expect(TechnicianFlagRecognition::query()->count())->toBe(1)
        ->and(TechnicianFlagRecognitionLine::query()->where('repair_order_line_id', $laborLine->id)->count())->toBe(1)
        ->and((float) TechnicianFlagRecognition::query()->sum('flag_hours_total'))->toBe(4.60);
});

test('newly added labor after reopen can be recognized without duplicating old labor', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $concern, $firstLine] = flagRecognitionFixture($technician, laborHours: 4.60);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::InProgress->value,
        ])
        ->assertRedirect();

    $secondLine = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Additional diagnosis',
        'quantity' => '2.00',
        'labor_billed_hours' => '2.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 30000,
        'tax_cents' => 0,
        'total_cents' => 30000,
    ]);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    expect(TechnicianFlagRecognition::query()->count())->toBe(2)
        ->and((float) TechnicianFlagRecognition::query()->sum('flag_hours_total'))->toBe(6.60);

    $second = TechnicianFlagRecognition::query()->orderByDesc('id')->first();

    expect($second->lines)->toHaveCount(1)
        ->and($second->lines->first()->repair_order_line_id)->toBe($secondLine->id)
        ->and((float) $second->flag_hours_total)->toBe(2.00)
        ->and(TechnicianFlagRecognitionLine::query()->where('repair_order_line_id', $firstLine->id)->count())->toBe(1);
});

test('missing assigned technician defers recognition without inventing attribution', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $concern] = flagRecognitionFixture(technician: null, laborHours: 3.00);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(TechnicianFlagRecognition::query()->count())->toBe(0);

    $deferred = OperationalEvent::query()
        ->where('event_name', OperationalEventName::FlagProductionRecognitionDeferred->value)
        ->latest('id')
        ->first();

    expect($deferred)->not->toBeNull()
        ->and($deferred->payload_json['reason'])->toBe('missing_assigned_technician');
});

test('changing flag floor rates creates compensation agreement history', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $technician = User::factory()->create([
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 2500,
        'floor_rate_cents' => 1516,
        'labor_cost_cents' => 8760,
    ])->assignRole(ArkRole::Technician->value);

    // Seed current agreement as migration would for Flag techs created before history writer.
    TechnicianCompensationAgreement::query()->create([
        'user_id' => $technician->id,
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 2500,
        'floor_rate_cents' => 1516,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.settings.staff.update', $technician), [
            'name' => $technician->name,
            'email' => $technician->email,
            'roles' => [ArkRole::Technician->value],
            'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
            'flag_rate' => '27.00',
            'floor_rate' => '15.16',
            'labor_cost' => '87.60',
        ])
        ->assertRedirect();

    $agreements = TechnicianCompensationAgreement::query()
        ->where('user_id', $technician->id)
        ->orderBy('id')
        ->get();

    expect($agreements)->toHaveCount(2)
        ->and($agreements[0]->flag_rate_cents)->toBe(2500)
        ->and($agreements[0]->effective_to)->not->toBeNull()
        ->and($agreements[0]->superseded_by_agreement_id)->toBe($agreements[1]->id)
        ->and($agreements[1]->flag_rate_cents)->toBe(2700)
        ->and($agreements[1]->floor_rate_cents)->toBe(1516)
        ->and($agreements[1]->effective_to)->toBeNull()
        ->and($technician->fresh()->flag_rate_cents)->toBe(2700)
        ->and($technician->fresh()->labor_cost_cents)->toBe(8760);

    expect(TechnicianCompensationAgreement::applicableAt(
        $technician->id,
        $agreements[0]->effective_from->copy()->addMinute(),
    )->flag_rate_cents)->toBe(2500);
});

test('new flag technician staff create writes compensation agreement history', function () {
    Notification::fake();
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->post(route('operations.settings.staff.store'), [
            'name' => 'History Tech',
            'email' => 'history-tech@ark.test',
            'roles' => [ArkRole::Technician->value],
            'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
            'flag_rate' => '25.00',
            'floor_rate' => '15.16',
            'labor_cost' => '32.00',
        ])
        ->assertRedirect();

    $tech = User::query()->where('email', 'history-tech@ark.test')->firstOrFail();
    $agreement = TechnicianCompensationAgreement::currentFor($tech->id);

    expect($agreement)->not->toBeNull()
        ->and($agreement->flag_rate_cents)->toBe(2500)
        ->and($agreement->floor_rate_cents)->toBe(1516)
        ->and($tech->flag_rate_cents)->toBe(2500);
});

test('phase 0 labor cost cents remains margin projection and no assist calculation runs', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $advisor = actingAsLearnCurrentAdvisor();
    $technician->forceFill([
        'labor_pay_basis' => TechnicianLaborPayBasis::Flag->value,
        'flag_rate_cents' => 2500,
        'floor_rate_cents' => 1516,
        'labor_cost_cents' => 8760,
    ])->save();

    [$repairOrder, $concern] = flagRecognitionFixture($technician, laborHours: 4.60);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    $recognition = TechnicianFlagRecognition::query()->first();

    expect($technician->fresh()->labor_cost_cents)->toBe(8760)
        ->and($recognition->getAttributes())->not->toHaveKey('flag_earnings_cents')
        ->and($recognition->getAttributes())->not->toHaveKey('floor_exposure_cents');
});

test('sublet lines never count toward recognized flag hours', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $concern, $laborLine] = flagRecognitionFixture($technician, laborHours: 2.00);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Sublet,
        'description' => 'Four-wheel alignment',
        'quantity' => '1.00',
        'unit_price_cents' => 12900,
        'subtotal_cents' => 12900,
        'tax_cents' => 0,
        'total_cents' => 12900,
    ]);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    $recognition = TechnicianFlagRecognition::query()->first();

    expect($recognition)->not->toBeNull()
        ->and((float) $recognition->flag_hours_total)->toBe(2.00)
        ->and($recognition->lines)->toHaveCount(1)
        ->and($recognition->lines->first()->repair_order_line_id)->toBe($laborLine->id);
});

test('deleting a recognized labor line removes flag recognition rows and succeeds', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $concern, $laborLine] = flagRecognitionFixture($technician, laborHours: 1.50);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect();

    expect(TechnicianFlagRecognitionLine::query()->where('repair_order_line_id', $laborLine->id)->exists())->toBeTrue();

    $this->actingAs($advisor)
        ->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $laborLine]))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(RepairOrderLine::query()->whereKey($laborLine->id)->exists())->toBeFalse()
        ->and(TechnicianFlagRecognitionLine::query()->where('repair_order_line_id', $laborLine->id)->exists())->toBeFalse()
        ->and(TechnicianFlagRecognition::query()->where('repair_order_concern_id', $concern->id)->exists())->toBeFalse();
});
