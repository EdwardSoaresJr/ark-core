<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Scoreboard\ShopOperatingScoreboard;
use App\Ark\Operations\Scoreboard\ShopOperatingScoreboardPeriod;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-24 18:00:00', 'America/Denver'));
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('dollar close uses customer-facing pre-tax dollars and ignores draft', function () {
    $mixed = scoreboardRepairOrder('2026-09-22 10:00:00', RepairOrderStatus::Estimate);
    scoreboardLabor($mixed, RepairOrderConcernDisposition::Approved, 10000);
    scoreboardLabor($mixed, RepairOrderConcernDisposition::Recommended, 10000);
    scoreboardLabor($mixed, RepairOrderConcernDisposition::Deferred, 5000);
    scoreboardLabor($mixed, RepairOrderConcernDisposition::Declined, 5000);

    $draft = scoreboardRepairOrder('2026-09-23 10:00:00', RepairOrderStatus::Draft);
    scoreboardLabor($draft, RepairOrderConcernDisposition::Draft, 80000);

    $snapshot = app(ShopOperatingScoreboard::class)->snapshot(ShopOperatingScoreboardPeriod::resolve('last_7'));
    $metrics = $snapshot['metrics'];

    expect($metrics['opened_count'])->toBe(2)
        ->and($metrics['open_days'])->toBe(5)
        ->and($metrics['ros_per_open_day'])->toBe(0.4)
        ->and($metrics['approved_cents'])->toBe(10000)
        ->and($metrics['presented_cents'])->toBe(30000)
        ->and($metrics['draft_cents'])->toBe(80000)
        ->and($metrics['dollar_close_percent'])->toBe(33.3)
        ->and($metrics['recommended_cents'])->toBe(10000)
        ->and($snapshot['previous']['opened_count'])->toBe(0)
        ->and($snapshot['previous']['dollar_close_percent'])->toBeNull();
});

test('median cycle stays put when one posted repair order is very long', function () {
    scoreboardPostedLabor('2026-09-21 10:00:00', '2026-09-22 10:00:00', 2);
    scoreboardPostedLabor('2026-09-22 10:00:00', '2026-09-23 10:00:00', 2);
    scoreboardPostedLabor('2026-08-25 10:00:00', '2026-09-24 10:00:00', 10);

    $metrics = app(ShopOperatingScoreboard::class)
        ->snapshot(ShopOperatingScoreboardPeriod::resolve('last_7'))['metrics'];

    expect($metrics['median_cycle_days'])->toBe(1.0)
        ->and($metrics['average_cycle_days'])->toBe(10.67)
        ->and($metrics['sold_hours'])->toBe(14.0)
        ->and($metrics['sold_hours_per_open_day'])->toBe(2.8)
        ->and($metrics['median_cycle_days'])->not->toBe($metrics['average_cycle_days']);
});

test('parts margin uses posted part cost and stays neutral until the shop activates a target', function () {
    $repairOrder = scoreboardPostedLabor('2026-09-22 09:00:00', '2026-09-22 15:00:00', 1);
    $concern = $repairOrder->concerns()->first();
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Low margin pad',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'part_cost_cents' => 8000,
        'vendor_name' => 'Bench Supplier',
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $snapshot = app(ShopOperatingScoreboard::class)->snapshot(ShopOperatingScoreboardPeriod::resolve('last_7'));

    expect($snapshot['metrics']['parts_margin_complete'])->toBeTrue()
        ->and($snapshot['metrics']['parts_margin_percent'])->toBe(20)
        ->and(collect($snapshot['kpis'])->firstWhere('key', 'parts_margin')['tone'])->toBeNull()
        ->and(collect($snapshot['kpis'])->firstWhere('key', 'parts_margin')['target_label'])->toBe('No target')
        ->and($snapshot['parts']['buckets'][1]['count'])->toBe(1);
});

test('an empty period does not divide by zero', function () {
    $metrics = app(ShopOperatingScoreboard::class)
        ->snapshot(ShopOperatingScoreboardPeriod::resolve('today'))['metrics'];

    expect($metrics['opened_count'])->toBe(0)
        ->and($metrics['dollar_close_percent'])->toBeNull()
        ->and($metrics['median_cycle_days'])->toBeNull()
        ->and($metrics['sold_hours_per_open_day'])->toBe(0.0);
});

test('lost no-response dollars are recommended dollars and are not treated as authorized', function () {
    $repairOrder = scoreboardRepairOrder('2026-09-20 10:00:00', RepairOrderStatus::Closed);
    scoreboardLabor($repairOrder, RepairOrderConcernDisposition::Recommended, 42000);
    $repairOrder->forceFill([
        'close_variant_key' => 'lost',
        'lost_reason_key' => RepairOrderLostReason::NoResponse,
        'lost_reason_recorded_at' => Carbon::parse('2026-09-22 12:00:00', 'America/Denver'),
    ])->save();

    $metrics = app(ShopOperatingScoreboard::class)
        ->snapshot(ShopOperatingScoreboardPeriod::resolve('last_7'))['metrics'];

    expect($metrics['lost_count'])->toBe(1)
        ->and($metrics['no_response_count'])->toBe(1)
        ->and($metrics['no_response_cents'])->toBe(42000)
        ->and($metrics['approved_cents'])->toBe(0);
});

test('advisor can open the full scoreboard and a technician cannot', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard'))
        ->assertOk()
        ->assertSee('Shop scoreboard')
        ->assertSee('Dollar close')
        ->assertSee('How dollar close is counted')
        ->assertSee('Authorized backlog')
        ->assertSee('Not authorized')
        ->assertSee('Presentation / decision needed');

    $this->actingAs($technician)
        ->get(route('operations.owner.scoreboard'))
        ->assertForbidden();
});

test('operational access does not include financial cards', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        ArkCapability::OperationsAccess->value,
        ArkCapability::ScoreboardOperationalView->value,
    ]);

    $this->actingAs($user)
        ->get(route('operations.owner.scoreboard'))
        ->assertOk()
        ->assertSee('ROs / open day')
        ->assertDontSee('Dollar close')
        ->assertDontSee('Money flow')
        ->assertDontSee('Authorized backlog');
});

test('wall mode refreshes without customer identity', function () {
    $repairOrder = scoreboardRepairOrder('2026-09-22 11:00:00', RepairOrderStatus::Estimate, 'Zelda', 'Hiddenname', '5557770142', 'zelda-hidden@example.test');
    scoreboardLabor($repairOrder, RepairOrderConcernDisposition::Recommended, 15000);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['display' => 'wall', 'period' => 'last_7']))
        ->assertOk()
        ->assertSee('Dollar close')
        ->assertSee('Updated')
        ->assertDontSee('Zelda')
        ->assertDontSee('Hiddenname')
        ->assertDontSee('zelda-hidden@example.test')
        ->assertDontSee('5557770142')
        ->assertDontSee('Follow-up queue');

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', [
            'display' => 'wall',
            'fragment' => 1,
            'period' => 'last_7',
            'focus' => 'follow_up',
        ]))
        ->assertOk()
        ->assertDontSee('Zelda')
        ->assertDontSee('zelda-hidden@example.test');

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['period' => 'last_7', 'focus' => 'follow_up']))
        ->assertOk()
        ->assertSee('Zelda Hiddenname')
        ->assertSee('No estimate contact on file')
        ->assertDontSee('zelda-hidden@example.test')
        ->assertDontSee('5557770142');
});

test('estimate contact is shown only from a communication event on the repair order', function () {
    $repairOrder = scoreboardRepairOrder('2026-09-22 11:00:00', RepairOrderStatus::WaitingApproval);
    scoreboardLabor($repairOrder, RepairOrderConcernDisposition::Recommended, 9000);
    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateSent,
        'channel' => OperationalCommunicationChannel::Email,
        'direction' => OperationalCommunicationDirection::Outbound,
        'summary' => 'Estimate handed over',
        'occurred_at' => Carbon::parse('2026-09-22 15:00:00', 'America/Denver'),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['period' => 'last_7', 'focus' => 'follow_up']))
        ->assertOk()
        ->assertSee('Estimate sent');
});

test('operating targets persist without becoming universal defaults', function () {
    expect(ShopExcellenceTargets::current()['opportunity_ros_per_open_day'])->toBeNull()
        ->and(ShopExcellenceTargets::current()['parts_margin_target_active'])->toBeFalse();

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.excellence.update'), [
            'aro_target' => '750.00',
            'parts_margin_target_percent' => 55,
            'labor_sales_target_percent' => 55,
            'parts_sales_target_percent' => 45,
            'net_profit_target_percent' => 20,
            'income_tax_reserve_percent' => 25,
            'payroll_tax_reserve_percent' => 10,
            'owner_digest_time' => '18:00',
            'opportunity_ros_per_open_day' => '2',
            'dollar_close_target_percent' => '60',
            'sold_labor_hours_per_open_day' => '5',
            'median_cycle_target_days' => '3',
        ])
        ->assertRedirect();

    $targets = ShopExcellenceTargets::current();

    expect($targets['opportunity_ros_per_open_day'])->toBe(2.0)
        ->and($targets['dollar_close_target_percent'])->toBe(60.0)
        ->and($targets['sold_labor_hours_per_open_day'])->toBe(5.0)
        ->and($targets['median_cycle_target_days'])->toBe(3.0)
        ->and($targets['stalled_ro_age_days'])->toBeNull()
        ->and($targets['parts_margin_target_active'])->toBeFalse();
});

test('current open work includes repair orders from before the reporting floor', function () {
    $old = scoreboardRepairOrder('2026-05-15 10:00:00', RepairOrderStatus::InProgress, 'Old', 'Floor');
    scoreboardLabor($old, RepairOrderConcernDisposition::Approved, 10000);
    $current = scoreboardRepairOrder('2026-09-20 10:00:00', RepairOrderStatus::Estimate, 'New', 'Month');
    scoreboardLabor($current, RepairOrderConcernDisposition::Approved, 5000);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $snapshot = app(ShopOperatingScoreboard::class)->snapshot(ShopOperatingScoreboardPeriod::resolve('this_month'));

    $mayWindow = OperationalReportDateScope::openedBetween(
        RepairOrder::query(),
        Carbon::parse('2026-05-01', 'America/Denver'),
        Carbon::parse('2026-05-31 23:59:59', 'America/Denver'),
    )->count();

    expect($snapshot['metrics']['opened_count'])->toBe(1)
        ->and($mayWindow)->toBe(0)
        ->and($snapshot['now']['open_count'])->toBe(2)
        ->and($snapshot['now']['authorized_cents'])->toBe(15000)
        ->and($snapshot['now']['authorized_count'])->toBe(2);

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['focus' => 'authorized']))
        ->assertOk()
        ->assertSee('Old Floor')
        ->assertSee('New Month');
});

test('not authorized backlog excludes approved and declined dollars', function () {
    $repairOrder = scoreboardRepairOrder('2026-09-10 10:00:00', RepairOrderStatus::Estimate);
    scoreboardLabor($repairOrder, RepairOrderConcernDisposition::Approved, 10000);
    scoreboardLabor($repairOrder, RepairOrderConcernDisposition::Declined, 80000);
    scoreboardLabor($repairOrder, RepairOrderConcernDisposition::Recommended, 2000);
    scoreboardLabor($repairOrder, RepairOrderConcernDisposition::Deferred, 4000);
    scoreboardLabor($repairOrder, RepairOrderConcernDisposition::Draft, 3000);

    $snapshot = app(ShopOperatingScoreboard::class)->snapshot(ShopOperatingScoreboardPeriod::resolve('this_month'));

    expect($snapshot['now']['authorized_cents'])->toBe(10000)
        ->and($snapshot['now']['authorized_count'])->toBe(1)
        ->and($snapshot['now']['not_authorized_cents'])->toBe(9000)
        ->and($snapshot['now']['not_authorized_count'])->toBe(1);
});

test('stalled age comes from shop configuration', function () {
    scoreboardRepairOrder('2026-09-10 10:00:00', RepairOrderStatus::WaitingApproval, 'Waiting', 'Long');
    scoreboardRepairOrder('2026-09-22 10:00:00', RepairOrderStatus::WaitingApproval, 'Waiting', 'Short');
    scoreboardRepairOrder('2026-09-01 10:00:00', RepairOrderStatus::WaitingApproval, 'Waiting', 'Older');
    scoreboardRepairOrder('2026-08-20 10:00:00', RepairOrderStatus::InProgress, 'In', 'Production');

    $snapshot = app(ShopOperatingScoreboard::class)->snapshot(ShopOperatingScoreboardPeriod::resolve('this_month'));
    $stalled = collect($snapshot['queues'])->firstWhere('key', 'stalled');

    expect($stalled['count'])->toBeNull();

    $targets = ShopExcellenceTargets::current();
    $targets['median_cycle_target_days'] = 3;
    ShopExcellenceTargets::persist($targets);

    $snapshot = app(ShopOperatingScoreboard::class)->snapshot(ShopOperatingScoreboardPeriod::resolve('this_month'));
    $stalled = collect($snapshot['queues'])->firstWhere('key', 'stalled');

    expect($stalled['count'])->toBe(2)
        ->and($stalled['hint'])->toContain('3.0 day cycle target');

    $targets['stalled_ro_age_days'] = 20;
    ShopExcellenceTargets::persist($targets);

    $snapshot = app(ShopOperatingScoreboard::class)->snapshot(ShopOperatingScoreboardPeriod::resolve('this_month'));
    $stalled = collect($snapshot['queues'])->firstWhere('key', 'stalled');

    expect($stalled['count'])->toBe(1)
        ->and($stalled['hint'])->toContain('20.0 days');
});

function scoreboardRepairOrder(
    string $openedAt,
    RepairOrderStatus $status,
    string $first = 'Ada',
    string $last = 'Customer',
    string $phone = '5550100',
    ?string $email = null,
): RepairOrder {
    $customer = Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'email' => $email,
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
        'status' => $status,
        'concern_summary' => 'Scoreboard fixture',
        'opened_at' => Carbon::parse($openedAt, 'America/Denver'),
    ]);
}

function scoreboardLabor(RepairOrder $repairOrder, RepairOrderConcernDisposition $disposition, int $cents): void
{
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $disposition->value,
        'disposition' => $disposition,
        'position' => $repairOrder->concerns()->count() + 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => $disposition->value.' labor',
        'quantity' => '1.00',
        'unit_price_cents' => $cents,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
}

function scoreboardPostedLabor(string $openedAt, string $postedAt, float $hours): RepairOrder
{
    $repairOrder = scoreboardRepairOrder($openedAt, RepairOrderStatus::ReadyPickup);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Posted labor',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Posted labor',
        'quantity' => $hours,
        'labor_billed_hours' => $hours,
        'unit_price_cents' => 10000,
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    $repairOrder->forceFill([
        'posted_at' => Carbon::parse($postedAt, 'America/Denver'),
    ])->save();

    return $repairOrder->fresh();
}
