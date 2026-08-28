<?php

use App\Ark\Operations\Business\BusinessWorkspaceAccess;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Staff\StaffFrontDoor;
use App\Ark\Operations\Today\Surface\TodayLens;
use App\Ark\Operations\Today\Surface\TodayProjectionBuilder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('staff front door lands on today for every role', function () {
    expect(StaffFrontDoor::landingRouteName(actingAsLearnCurrentAdvisor()))->toBe('operations.today');
    expect(StaffFrontDoor::landingRouteName(actingAsLearnCurrentStaff(ArkRole::Technician)))->toBe('operations.today');
    expect(StaffFrontDoor::landingRouteName(
        User::factory()->create()->assignRole(ArkRole::Admin->value)
    ))->toBe('operations.today');
});

test('advisor today is shop dashboard without pressure theater', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Dash Customer');

    $concern = $repairOrder->concerns()->first();
    if ($concern !== null) {
        $concern->forceFill(['disposition' => RepairOrderConcernDisposition::Recommended->value])->save();
    }

    $projection = app(TodayProjectionBuilder::class)->forUser($advisor);

    expect($projection->lens)->toBe(TodayLens::Advisor)
        ->and($projection->showsShopDashboard())->toBeTrue()
        ->and($projection->shopDashboard)->not->toBeNull()
        ->and($projection->shopDashboard->carCount)->toBeGreaterThan(0)
        ->and($projection->nonEmptySections())->toBeEmpty();

    $pendingDrill = route('operations.repair-orders.index', [
        'open' => '1',
        'disposition' => RepairOrderConcernDisposition::Recommended->value,
    ]);
    $statusDrill = route('operations.repair-orders.index', [
        'open' => '1',
        'status' => RepairOrderStatus::WaitingApproval->value,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Shop Dashboard')
        ->assertSee('Car Count')
        ->assertSee('Pending Sales')
        ->assertSee('Declined Sales')
        ->assertSee('Approved Sales')
        ->assertSee('ARO')
        ->assertSee('Close Ratio')
        ->assertSee(e($pendingDrill), false)
        ->assertSee(e($statusDrill), false)
        ->assertDontSee('ops-today-cockpit', false)
        ->assertDontSee('Market pressure')
        ->assertDontSee('Why today?')
        ->assertDontSee('Needs Attention');
});

test('admin today is shop dashboard not owner metrics dump', function () {
    $owner = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    completeRequiredLearnFor($owner);

    repairOrderForCommunication(RepairOrderStatus::InProgress, 'Owner Dash Customer');

    $projection = app(TodayProjectionBuilder::class)->forUser($owner);

    expect($projection->lens)->toBe(TodayLens::Owner)
        ->and($projection->showsShopDashboard())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Shop Dashboard')
        ->assertSee('Pending Sales')
        ->assertDontSee('Market pressure')
        ->assertDontSee('This week')
        ->assertDontSee('Publish P0171');
});

test('technician lens still surfaces assigned work lanes', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);

    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Tech Customer');
    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();

    $projection = app(TodayProjectionBuilder::class)->forUser($technician);

    expect($projection->lens)->toBe(TodayLens::Technician)
        ->and($projection->showsShopDashboard())->toBeFalse();

    $titles = array_map(static fn ($section) => $section->title, $projection->nonEmptySections());

    expect($titles)->toContain('Assigned work');

    $this->actingAs($technician)
        ->get(route('operations.today'))
        ->assertOk()
        ->assertSee('Assigned work')
        ->assertDontSee('Shop Dashboard')
        ->assertSee(route('operations.repair-orders.show', $repairOrder), false);
});

test('business cockpit remains permission gated', function () {
    $owner = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    completeRequiredLearnFor($owner);

    expect(BusinessWorkspaceAccess::allows($owner))->toBeTrue();

    $this->actingAs($owner)
        ->get(route('operations.business'))
        ->assertOk()
        ->assertSee('Business');

    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    expect(BusinessWorkspaceAccess::allows($technician))->toBeFalse();

    $this->actingAs($technician)
        ->get(route('operations.business'))
        ->assertForbidden();
});

test('shop dashboard close ratio uses approved over total written', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $approvedRo = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Approved Dash');
    $approvedConcern = $approvedRo->concerns()->first();
    expect($approvedConcern)->not->toBeNull();
    $approvedConcern->forceFill(['disposition' => RepairOrderConcernDisposition::Approved->value])->save();

    $projection = app(TodayProjectionBuilder::class)->forUser($advisor);
    $dash = $projection->shopDashboard;

    expect($dash)->not->toBeNull()
        ->and($dash->carCount)->toBeGreaterThan(0)
        ->and($dash->kpis)->toHaveCount(6)
        ->and($dash->kpis[0]['url'])->toContain('open=1')
        ->and($dash->kpis[1]['url'])->toContain('disposition=recommended')
        ->and($dash->kpis[3]['url'])->toContain('disposition=approved');

    $labels = array_column($dash->kpis, 'label');
    expect($labels)->toBe([
        'Car Count',
        'Pending Sales',
        'Declined Sales',
        'Approved Sales',
        'ARO',
        'Close Ratio',
    ]);
});

test('repair order index filters open queue by disposition for dashboard drill-down', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $pending = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Pending Drill');
    $pending->concerns()->first()?->forceFill([
        'disposition' => RepairOrderConcernDisposition::Recommended->value,
    ])->save();

    $approved = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Approved Drill');
    $approved->concerns()->first()?->forceFill([
        'disposition' => RepairOrderConcernDisposition::Approved->value,
    ])->save();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index', [
            'open' => '1',
            'disposition' => RepairOrderConcernDisposition::Recommended->value,
        ]))
        ->assertOk()
        ->assertSee('Pending Drill')
        ->assertDontSee('Approved Drill');
});

test('briefing route redirects to today', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.briefing'))
        ->assertRedirect(route('operations.today'));
});

test('business workspace access matches route authorization', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    expect(BusinessWorkspaceAccess::allows($advisor))->toBeTrue();

    $this->actingAs($advisor)
        ->get(route('operations.business'))
        ->assertOk();

    $stripped = User::factory()->create(['name' => 'No Biz']);
    completeRequiredLearnFor($stripped);
    $stripped->givePermissionTo([
        ArkCapability::ProductionAccess->value,
        ArkCapability::OperationsAccess->value,
    ]);

    expect(BusinessWorkspaceAccess::allows($stripped))->toBeFalse();

    $this->actingAs($stripped)
        ->get(route('operations.business'))
        ->assertForbidden();
});
