<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Friday evening in Denver while UTC is already Saturday.
    Carbon::setTestNow(Carbon::parse('2026-06-06 04:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('financial users see operational reporting without dashboard theater', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create(['name' => 'Tina Tech'])->assignRole(ArkRole::Technician->value);

    $this->actingAs($advisor);

    operationalReportingFixture($technician);

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
    ]))
        ->assertOk()
        ->assertSee('Executive Pulse')
        ->assertSee('End of Day card')
        ->assertSee('Sales Posted')
        ->assertSee('$550.00')
        ->assertSee('Car Count')
        ->assertSee('ARO')
        ->assertSee('Approval Rate')
        ->assertSee('Unpaid Pickups')
        ->assertSee('$180.00')
        ->assertSee('Labor Sold')
        ->assertSee('2.0 billed hours')
        ->assertSee('Parts Sold')
        ->assertSee('$200.00')
        ->assertSee('Fees Sold')
        ->assertSee('$50.00')
        ->assertSee('Effective Labor Rate')
        ->assertSee('Parts Margin')
        ->assertSee('Parts/Labor Mix')
        ->assertSee('Labor Margin')
        ->assertSee('Parts GP')
        ->assertSee('$110.00')
        ->assertSee('Deferred Opportunity')
        ->assertSee('$120.00')
        ->assertDontSee('Forecast')
        ->assertDontSee('AI insight')
        ->assertDontSee('Campaign');
});

test('end of day report projection surfaces tekmetric style sections for posted sales', function () {
    $technician = User::factory()->create(['name' => 'Tina Tech'])->assignRole(ArkRole::Technician->value);
    operationalReportingFixture($technician);

    [$from, $to] = OperationalReportDateScope::resolveRange(
        operationalReportingShopToday(),
        operationalReportingShopToday(),
    );

    $eod = \App\Ark\Operations\Reports\EndOfDayReportProjection::resolve($from, $to);

    expect(collect($eod->salesEffectiveness)->pluck('label')->all())
        ->toContain('Total ROs', 'Hours Sold', 'Effective Labor Rate')
        ->and(collect($eod->roSummary)->pluck('label')->all())
        ->toContain('Posted Total')
        ->and(collect($eod->salesBreakdown)->pluck('category')->all())
        ->toContain('Labor', 'Parts');
});

test('operational report surfaces pressure production financial mix and deferred opportunity', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create(['name' => 'Tina Tech'])->assignRole(ArkRole::Technician->value);

    $this->actingAs($advisor);

    operationalReportingFixture($technician);

    $range = [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
    ];

    $this->get(route('operations.reports.operational', array_merge($range, ['tab' => 'production'])))
        ->assertOk()
        ->assertSee('Approvals aging')
        ->assertSee('Follow up customer authorization')
        ->assertSee('Parts backlog')
        ->assertSee('1 backordered')
        ->assertSee('Ready execution')
        ->assertSee('Unpaid pickup')
        ->assertSee('Collect before release')
        ->assertSee('Technician Production')
        ->assertSee('Tina Tech')
        ->assertSee('2.0 hrs on board')
        ->assertSee('efficiency');

    $this->get(route('operations.reports.operational', array_merge($range, ['tab' => 'financial'])))
        ->assertOk()
        ->assertSee('Financial Mix')
        ->assertSee('Labor')
        ->assertSee('Parts')
        ->assertSee('known part costs only')
        ->assertSee('Deferred Work Opportunity')
        ->assertSee('Deferred work')
        ->assertSee('Recent Posts')
        ->assertSee('Closed Customer');
});

test('sales closed excludes imported carryover opened before the report range', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $closedInRange = now()->startOfMonth()->addDay();
    $carryoverOpened = $closedInRange->copy()->subYear();

    $customer = Customer::query()->create([
        'first_name' => 'Carryover',
        'last_name' => 'Import',
        'phone' => '5550188',
        'legacy_arksms_customer_id' => 88001,
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'legacy_arksms_vehicle_id' => 88002,
    ]);

    $carryover = RepairOrder::query()->create([
        'repair_order_id' => 88003,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Prior-year import carryover.',
        'opened_at' => $carryoverOpened,
        'closed_at' => $closedInRange,
        'posted_at' => $closedInRange,
        'paid_at' => $closedInRange,
        'payment_status' => RepairOrderPaymentStatus::Paid,
        'created_at' => $carryoverOpened,
        'updated_at' => now(),
    ]);

    $concern = concernForOperationalReporting($carryover, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($carryover, $concern, RepairOrderLineType::Labor, 99900, quantity: '1.00');

    $native = repairOrderForOperationalReporting('Native Close', RepairOrderStatus::Closed);
    $native->forceFill([
        'opened_at' => $closedInRange->copy()->startOfDay(),
    ])->save();
    closeForOperationalReporting($native, $closedInRange->copy()->endOfDay());
    $nativeConcern = concernForOperationalReporting($native, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($native, $nativeConcern, RepairOrderLineType::Labor, 10000, quantity: '1.00');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopMonthStart(),
        'to' => operationalReportingShopToday(),
    ]))
        ->assertOk()
        ->assertSee('$100.00')
        ->assertDontSee('$999.00')
        ->assertDontSee('$1,099.00');
});

test('sales closed includes ready pickup repair orders closed in range', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $closedAt = now()->subDay();

    $pickup = repairOrderForOperationalReporting('Pickup Closed Customer', RepairOrderStatus::ReadyPickup);
    closeForOperationalReporting($pickup, $closedAt);
    $pickupConcern = concernForOperationalReporting($pickup, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($pickup, $pickupConcern, RepairOrderLineType::Labor, 25000, quantity: '1.00');

    $this->get(route('operations.reports.operational', [
        'from' => OperationalReportDateScope::shopDateString($closedAt->copy()->subDays(3)),
        'to' => operationalReportingShopToday(),
    ]))
        ->assertOk()
        ->assertSee('$250.00');
});

test('sales closed excludes deferred and recommended lines from totals', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForOperationalReporting('Sold Only Customer', RepairOrderStatus::Closed);
    closeForOperationalReporting($repairOrder);

    $approvedConcern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $approvedConcern, RepairOrderLineType::Labor, 10000, quantity: '1.00');

    $deferredConcern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Deferred);
    lineForOperationalReporting($repairOrder, $deferredConcern, RepairOrderLineType::Part, 50000, quantity: '1.00');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
    ]))
        ->assertOk()
        ->assertSee('$100.00')
        ->assertDontSee('$600.00');
});

test('operational report attributes imported closed work to close date not last update', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Imported',
        'last_name' => 'History',
        'phone' => '5550199',
        'legacy_arksms_customer_id' => 99001,
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'Fusion',
        'legacy_arksms_vehicle_id' => 99002,
    ]);

    $closedAt = OperationalReportDateScope::trustworthyDataStartsAt()->addDays(2)->startOfDay()->addHours(10);
    $openedAt = $closedAt->copy()->subDay()->startOfDay()->addHours(9);

    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 99003,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Imported legacy brake job.',
        'opened_at' => $openedAt,
        'closed_at' => $closedAt,
        'posted_at' => $closedAt,
        'paid_at' => $closedAt,
        'payment_status' => RepairOrderPaymentStatus::Paid,
        'created_at' => $closedAt->copy()->subDays(4),
        'updated_at' => now(),
    ]);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 12500, quantity: '1.00');

    $rangeFrom = OperationalReportDateScope::shopDateString($closedAt->copy()->subDay());
    $rangeTo = OperationalReportDateScope::shopDateString($closedAt->copy()->addDay());

    $this->get(route('operations.reports.operational', [
        'from' => $rangeFrom,
        'to' => $rangeTo,
        'tab' => 'financial',
    ]))
        ->assertOk()
        ->assertSee('Imported History')
        ->assertSee('$125.00');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
    ]))
        ->assertOk()
        ->assertDontSee('Imported History');
});

test('operational report excludes repair orders opened before trustworthy data cutoff', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $cutoff = OperationalReportDateScope::trustworthyDataStartsAt();
    $openedBeforeCutoff = $cutoff->copy()->subDays(10);
    $closedInRange = $cutoff->copy()->addDays(3);

    $repairOrder = repairOrderForOperationalReporting('Dirty History Customer', RepairOrderStatus::Closed);
    $repairOrder->forceFill([
        'opened_at' => $openedBeforeCutoff,
        'closed_at' => $closedInRange,
        'updated_at' => now(),
    ])->save();

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 45000, quantity: '1.00');

    $this->get(route('operations.reports.operational', [
        'from' => OperationalReportDateScope::shopDateString($cutoff),
        'to' => operationalReportingShopToday(),
    ]))
        ->assertOk()
        ->assertDontSee('Dirty History Customer')
        ->assertDontSee('$450.00');
});

test('admin can hold multiple operational roles and set loaded labor cost', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.staff.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [
                ArkRole::Admin->value,
                ArkRole::Advisor->value,
                ArkRole::Technician->value,
            ],
            'labor_cost' => '42.50',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'staff']));

    $admin->refresh();

    expect($admin->hasRole(ArkRole::Admin->value))->toBeTrue()
        ->and($admin->hasRole(ArkRole::Advisor->value))->toBeTrue()
        ->and($admin->hasRole(ArkRole::Technician->value))->toBeTrue()
        ->and($admin->labor_cost_cents)->toBe(4250);
});

test('operational report uses assigned technician loaded labor cost', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $technician = User::factory()->create(['labor_cost_cents' => 4000])->assignRole(ArkRole::Technician->value);

    $repairOrder = repairOrderForOperationalReporting('Labor Cost Customer', RepairOrderStatus::Closed);
    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 20000, quantity: '2.00');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'financial',
    ]))
        ->assertOk()
        ->assertSee('$80.00');
});

test('technician efficiency capacity follows shop communications hours not generic weekdays', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);
    $this->actingAs($advisor);

    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'weekly_hours' => array_merge(ShopSettings::defaultTelephonyCallFlow()['weekly_hours'], [
                'saturday' => ['enabled' => true, 'open' => '09:00', 'close' => '13:00'],
            ]),
        ]),
    ]);

    $technician = User::factory()->create([
        'name' => 'Landon Tech',
        'workday_hours' => 8,
    ])->assignRole(ArkRole::Technician->value);

    $this->get(route('operations.reports.operational', [
        'from' => '2026-06-01',
        'to' => '2026-06-07',
        'tab' => 'production',
    ]))
        ->assertOk()
        ->assertSee('48.0 hr capacity');

    ShopSettings::current()->update([
        'telephony_call_flow' => ShopSettings::defaultTelephonyCallFlow(),
    ]);

    $this->get(route('operations.reports.operational', [
        'from' => '2026-06-01',
        'to' => '2026-06-07',
        'tab' => 'production',
    ]))
        ->assertOk()
        ->assertSee('40.0 hr capacity')
        ->assertDontSee('48.0 hr capacity');
});

test('technician efficiency uses closed billed hours against weekday workday capacity', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $technician = User::factory()->create([
        'workday_hours' => 8,
    ])->assignRole(ArkRole::Technician->value);

    $repairOrder = repairOrderForOperationalReporting('Efficiency Customer', RepairOrderStatus::Closed);
    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 30000, quantity: '6.00');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'production',
    ]))
        ->assertOk()
        ->assertSee('75% efficiency')
        ->assertSee('6.0 billed / 8.0 hr capacity');
});

test('technician efficiency honors custom workday hours override', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $technician = User::factory()->create([
        'workday_hours' => 10,
    ])->assignRole(ArkRole::Technician->value);

    $repairOrder = repairOrderForOperationalReporting('Ten Hour Customer', RepairOrderStatus::Closed);
    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 50000, quantity: '5.00');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'production',
    ]))
        ->assertOk()
        ->assertSee('50% efficiency')
        ->assertSee('5.0 billed / 10.0 hr capacity');
});

test('operational report defaults and weekday capacity follow shop display timezone', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    expect(operationalReportingShopToday())->toBe('2026-06-05')
        ->and(now()->toDateString())->toBe('2026-06-06');

    $technician = User::factory()->create([
        'workday_hours' => 8,
    ])->assignRole(ArkRole::Technician->value);

    $repairOrder = repairOrderForOperationalReporting('Timezone Customer', RepairOrderStatus::Closed);
    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 30000, quantity: '6.00');

    $this->get(route('operations.reports.operational', [
        'tab' => 'production',
    ]))
        ->assertOk()
        ->assertSee('Jun 1, 2026–Jun 5, 2026')
        ->assertSee('15% efficiency');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'production',
    ]))
        ->assertOk()
        ->assertSee('75% efficiency');

    $this->get(route('operations.reports.operational', [
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
        'tab' => 'production',
    ]))
        ->assertOk()
        ->assertSee('No shop open days in range');
});

test('operational report executive pulse calculates effective labor rate and parts margin on closed sales', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForOperationalReporting('Margin KPI Customer', RepairOrderStatus::Closed);
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 30000, quantity: '2.00');
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Part, 20000, partCostCents: 9000);

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
    ]))
        ->assertOk()
        ->assertSee('Effective Labor Rate')
        ->assertSee('$150.00/hr')
        ->assertSee('Parts Margin')
        ->assertSee('55%')
        ->assertSee('Parts/Labor Mix')
        ->assertSee('40% parts · 60% labor');
});

test('operational report margin health tab compares metrics to shop targets', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForOperationalReporting('Margin Health Customer', RepairOrderStatus::Closed);
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 30000, quantity: '2.00');
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Part, 20000, partCostCents: 9000);

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'margin-health',
    ]))
        ->assertOk()
        ->assertSee('Margin Health')
        ->assertSee('Effective labor rate')
        ->assertSee('Parts margin')
        ->assertSee('55%')
        ->assertSee('Follow the parts matrix');
});

test('margin health tab shows break even pulse when monthly fixed costs are configured', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopExcellenceTargets::persist(array_merge(
        ShopExcellenceTargets::DEFAULTS,
        ['monthly_fixed_costs_cents' => 3000000],
    ));

    $repairOrder = repairOrderForOperationalReporting('Break Even Customer', RepairOrderStatus::Closed);
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 30000, quantity: '2.00');
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Part, 20000, partCostCents: 9000);

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'margin-health',
    ]))
        ->assertOk()
        ->assertSee('Break-even pulse')
        ->assertSee('Prorated fixed')
        ->assertSee('Monthly break-even sales');
});

test('owner pl tab shows management p and l tax posture and net benchmark', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopExcellenceTargets::persist(array_merge(
        ShopExcellenceTargets::DEFAULTS,
        [
            'monthly_fixed_costs_cents' => 3000000,
            'net_profit_target_percent' => 20,
        ],
    ));

    $repairOrder = repairOrderForOperationalReporting('Owner PL Customer', RepairOrderStatus::Closed);
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 30000, quantity: '2.00', shopFeeCents: 1500);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Part, 20000, partCostCents: 9000, taxCents: 800, shopFeeCents: 500);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Fee, 5000);

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'owner-pl',
    ]))
        ->assertOk()
        ->assertSee('Owner P&amp;L', false)
        ->assertSee('Service revenue (pre-tax)')
        ->assertSee('Gross profit')
        ->assertSee('Operating expenses')
        ->assertSee('Operating income (est.)')
        ->assertSee('Sales tax to remit')
        ->assertSee('$8.00')
        ->assertSee('Net profit benchmark')
        ->assertSee('20% target')
        ->assertSee('Management estimate from posted sales')
        ->assertSee('Shop fees sold')
        ->assertSee('$70.00');
});

test('owner pl tab splits advisor payroll from shop fixed overhead', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'shop_overhead_state' => [
            'fixed_cost_lines' => [
                ['label' => 'Payroll', 'amount' => '9000', 'period' => 'monthly'],
                ['label' => 'Rent', 'amount' => '3300', 'period' => 'monthly'],
            ],
        ],
    ]);

    ShopExcellenceTargets::persist(array_merge(
        ShopExcellenceTargets::DEFAULTS,
        ['monthly_fixed_costs_cents' => 1230000],
    ));

    $repairOrder = repairOrderForOperationalReporting('Advisor Payroll PL', RepairOrderStatus::Closed);
    closeForOperationalReporting($repairOrder);

    $concern = concernForOperationalReporting($repairOrder, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($repairOrder, $concern, RepairOrderLineType::Labor, 30000, quantity: '2.00');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'owner-pl',
    ]))
        ->assertOk()
        ->assertSee('Advisor / office payroll')
        ->assertSee('Shop fixed overhead')
        ->assertSee('Shop Overhead → Office and advisor payroll')
        ->assertSee('$591.39')
        ->assertSee('$216.84');
});

test('technician cannot access financial operational reports', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($technician)
        ->get(route('operations.reports.operational'))
        ->assertForbidden();
});

test('operational intelligence sections surface queue approval liability and conversion truth', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor);

    $waitingCustomer = repairOrderForOperationalReporting('Waiting Customer', RepairOrderStatus::WaitingApproval);
    $waitingConcern = concernForOperationalReporting($waitingCustomer, RepairOrderConcernDisposition::Recommended, 'immediate_attention');
    lineForOperationalReporting($waitingCustomer, $waitingConcern, RepairOrderLineType::Labor, 15000, quantity: '1.00');

    CommunicationEvent::query()->create([
        'repair_order_id' => $waitingCustomer->id,
        'event_type' => OperationalCommunicationType::EstimateSent,
        'channel' => OperationalCommunicationChannel::Email,
        'direction' => OperationalCommunicationDirection::Outbound,
        'summary' => 'Estimate emailed',
        'occurred_at' => now()->subDay(),
    ]);

    $courtesy = repairOrderForOperationalReporting('Courtesy Customer', RepairOrderStatus::Closed);
    closeForOperationalReporting($courtesy);
    $courtesyConcern = concernForOperationalReporting($courtesy, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting(
        $courtesy,
        $courtesyConcern,
        RepairOrderLineType::Labor,
        0,
        quantity: '1.50',
        laborCategoryKey: 'courtesy',
    );

    $deferredSafety = repairOrderForOperationalReporting('Deferred Safety', RepairOrderStatus::Estimate);
    $deferredConcern = concernForOperationalReporting($deferredSafety, RepairOrderConcernDisposition::Deferred, 'immediate_attention');
    lineForOperationalReporting($deferredSafety, $deferredConcern, RepairOrderLineType::Labor, 8500, quantity: '1.00');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
    ]))
        ->assertOk()
        ->assertSee('Executive Pulse')
        ->assertSee('Operations')
        ->assertSee('Operational Truth')
        ->assertSee('Waiting Approval')
        ->assertSee('Waiting Customer')
        ->assertSee('Approval Momentum')
        ->assertSee('Awaiting Response')
        ->assertSee('Labor Liability')
        ->assertSee('Courtesy')
        ->assertSee('Total Non-Billable')
        ->assertSee('Recommendation Conversion')
        ->assertSee('Safety / Drivability')
        ->assertSee('$85.00')
        ->assertDontSee('Financial Mix')
        ->assertDontSee('Technician Production');

    $this->get(route('operations.reports.operational', [
        'from' => operationalReportingShopToday(),
        'to' => operationalReportingShopToday(),
        'tab' => 'financial',
    ]))
        ->assertOk()
        ->assertSee('Financial Mix')
        ->assertDontSee('Approval Momentum');
});

function operationalReportingShopToday(): string
{
    return OperationalReportDateScope::shopDateString(now());
}

function operationalReportingShopMonthStart(): string
{
    return OperationalReportDateScope::shopDateString(
        OperationalReportDateScope::shopNow()->copy()->startOfMonth(),
    );
}

function operationalReportingOpenedAt(): Carbon
{
    return OperationalReportDateScope::shopNow()->copy()->startOfDay()->addHours(9)->timezone(config('app.timezone'));
}

function markPostedForOperationalReporting(RepairOrder $repairOrder, ?Carbon $postedAt = null): void
{
    $postedAt ??= now();

    $repairOrder->forceFill([
        'payment_status' => RepairOrderPaymentStatus::Paid,
        'paid_at' => $postedAt,
        'posted_at' => $postedAt,
    ])->save();
}

function closeForOperationalReporting(RepairOrder $repairOrder, ?Carbon $closedAt = null): void
{
    $closedAt ??= now();

    $repairOrder->forceFill([
        'opened_at' => $repairOrder->opened_at ?? operationalReportingOpenedAt(),
        'closed_at' => $closedAt,
        'updated_at' => now(),
    ])->save();

    markPostedForOperationalReporting($repairOrder, $closedAt);
}

function markSalesClosedForOperationalReporting(RepairOrder $repairOrder, ?Carbon $paidAt = null): void
{
    markPostedForOperationalReporting($repairOrder, $paidAt);
}

function operationalReportingFixture(User $technician): void
{
    $closed = repairOrderForOperationalReporting('Closed Customer', RepairOrderStatus::Closed);
    closeForOperationalReporting($closed);
    $closedConcern = concernForOperationalReporting($closed, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($closed, $closedConcern, RepairOrderLineType::Labor, 30000, quantity: '2.00');
    lineForOperationalReporting($closed, $closedConcern, RepairOrderLineType::Part, 20000, partCostCents: 9000);
    lineForOperationalReporting($closed, $closedConcern, RepairOrderLineType::Fee, 5000);

    $waitingApproval = repairOrderForOperationalReporting('Approval Customer', RepairOrderStatus::WaitingApproval);
    $waitingConcern = concernForOperationalReporting($waitingApproval, RepairOrderConcernDisposition::Recommended);
    lineForOperationalReporting($waitingApproval, $waitingConcern, RepairOrderLineType::Labor, 20000, quantity: '1.00');
    lineForOperationalReporting($waitingApproval, $waitingConcern, RepairOrderLineType::Part, 10000, partCostCents: 6000);

    $unpaidPickup = repairOrderForOperationalReporting('Pickup Customer', RepairOrderStatus::ReadyPickup);
    $unpaidPickup->forceFill(['payment_status' => RepairOrderPaymentStatus::Unpaid])->save();
    $pickupConcern = concernForOperationalReporting($unpaidPickup, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($unpaidPickup, $pickupConcern, RepairOrderLineType::Labor, 10000, quantity: '1.00');
    lineForOperationalReporting($unpaidPickup, $pickupConcern, RepairOrderLineType::Part, 8000, partCostCents: 3000);

    $partsBlocked = repairOrderForOperationalReporting('Parts Customer', RepairOrderStatus::WaitingParts);
    $partsBlocked->forceFill(['assigned_technician_id' => $technician->id])->save();
    $partsConcern = concernForOperationalReporting($partsBlocked, RepairOrderConcernDisposition::Approved);
    lineForOperationalReporting($partsBlocked, $partsConcern, RepairOrderLineType::Labor, 30000, quantity: '2.00');
    lineForOperationalReporting($partsBlocked, $partsConcern, RepairOrderLineType::Part, 9000, partCostCents: 5000, procurementState: PartProcurementState::Backordered);

    $deferred = repairOrderForOperationalReporting('Deferred Customer', RepairOrderStatus::Estimate);
    $deferredConcern = concernForOperationalReporting($deferred, RepairOrderConcernDisposition::Deferred);
    lineForOperationalReporting($deferred, $deferredConcern, RepairOrderLineType::Labor, 12000, quantity: '1.00');
}

function repairOrderForOperationalReporting(string $customerName, RepairOrderStatus $status): RepairOrder
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Operational report fixture.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function concernForOperationalReporting(
    RepairOrder $repairOrder,
    RepairOrderConcernDisposition $disposition,
    string $recommendationIntent = 'maintenance',
    ?string $billingPosture = null,
): RepairOrderConcern {
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $disposition->label().' service',
        'disposition' => $disposition,
        'recommendation_intent' => $recommendationIntent,
        'billing_posture' => $billingPosture ?? 'default',
        'position' => 1,
    ]);
}

function lineForOperationalReporting(
    RepairOrder $repairOrder,
    RepairOrderConcern $concern,
    RepairOrderLineType $type,
    int $subtotalCents,
    string $quantity = '1.00',
    ?int $partCostCents = null,
    ?PartProcurementState $procurementState = null,
    ?string $laborCategoryKey = null,
    int $taxCents = 0,
    int $shopFeeCents = 0,
): RepairOrderLine {
    $quantityValue = (float) $quantity;
    $unitPriceCents = $quantityValue > 0 ? (int) round($subtotalCents / $quantityValue) : $subtotalCents;

    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => $type,
        'description' => $type->value.' line',
        'quantity' => $quantity,
        'unit_price_cents' => $unitPriceCents,
        'part_cost_cents' => $partCostCents,
        'procurement_state' => $procurementState ?? PartProcurementState::None,
        'labor_category_key' => $laborCategoryKey,
        'subtotal_cents' => $subtotalCents,
        'tax_cents' => $taxCents,
        'shop_fee_cents' => $shopFeeCents,
        'total_cents' => $subtotalCents + $shopFeeCents + $taxCents,
    ]);
}
