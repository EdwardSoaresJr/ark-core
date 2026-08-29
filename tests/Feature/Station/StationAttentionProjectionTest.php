<?php

use App\Ark\Dragon\Agent\DragonModelTurn;
use App\Ark\Dragon\Agent\Providers\FakeDragonProvider;
use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentKind;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Station\StationAttentionProjection;
use App\Ark\Station\StationDeviceToken;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['shop.identity' => 'test.demo-auto.local']);
    config(['dragon.provider' => 'fake']);
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
    ]);
    ShopSettings::forgetCurrent();
    Carbon::setTestNow(Carbon::parse('2026-08-23 16:00:00', 'America/Denver'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function attentionCustomer(string $first, string $last, string $phone): Customer
{
    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'email' => strtolower($first).'@hidden.test',
    ]);
}

function attentionVehicle(Customer $customer, int $year, string $make, string $model): Vehicle
{
    return Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => $year,
        'make' => $make,
        'model' => $model,
    ]);
}

function attentionRepairOrder(
    RepairOrderStatus $status,
    Vehicle $vehicle,
    Customer $customer,
    array $overrides = [],
): RepairOrder {
    return RepairOrder::query()->create(array_merge([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Attention fixture',
        'assigned_technician_id' => null,
        'opened_at' => now()->subDay(),
    ], $overrides));
}

function attentionRecommendedLabor(RepairOrder $repairOrder, int $cents): void
{
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Recommended work',
        'disposition' => RepairOrderConcernDisposition::Recommended->value,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor',
        'quantity' => '1.00',
        'unit_price_cents' => $cents,
        'subtotal_cents' => $cents,
        'total_cents' => $cents,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.00',
        'labor_billed_hours' => '1.00',
        'labor_rate_cents' => $cents,
        'position' => 1,
    ]);
}

test('waiting approval is in attention with forecast pending dollars', function (): void {
    $customer = attentionCustomer('Dana', 'Miles', '7195550101');
    $vehicle = attentionVehicle($customer, 2006, 'Chevrolet', 'Silverado');
    $repairOrder = attentionRepairOrder(RepairOrderStatus::WaitingApproval, $vehicle, $customer, [
        'opened_at' => now()->subDays(31),
        'repair_order_id' => 1597,
    ]);
    attentionRecommendedLabor($repairOrder, 184200);

    $payload = app(StationAttentionProjection::class)->payload();
    $row = collect($payload['rows'])->firstWhere('repair_order_id', 1597);

    expect($row)->not->toBeNull()
        ->and($row['attention_reasons'])->toContain('waiting_approval')
        ->and($row['waiting_approval_amount_cents'])->toBe(184200)
        ->and($row['waiting_approval_amount_label'])->toBe('$1,842')
        ->and($row['age_days'])->toBeGreaterThanOrEqual(30)
        ->and($row['age_days'])->toBeLessThanOrEqual(31)
        ->and($row['open_in_ark_url'])->toContain('/app/repair-orders/1597')
        ->and($payload['shop_summary']['waiting_approval_amount_cents'])->toBe(184200)
        ->and($payload['shop_summary']['money_semantics'])->toContain('Not posted sales')
        ->and($payload['snapshot']['shop_summary'])->not->toHaveKey('waiting_approval_amount_cents')
        ->and($payload['snapshot']['shop_summary']['waiting_approval_amount'])->toBe('$1,842')
        ->and($payload['snapshot']['rows'][0])->not->toHaveKey('waiting_approval_amount_cents')
        ->and($payload['snapshot']['rows'][0]['waiting_approval_amount'])->toBe('$1,842');
});

test('healthy assigned in-progress work is not attention', function (): void {
    $tech = User::factory()->create(['name' => 'Landon Carter']);
    $customer = attentionCustomer('Healthy', 'Job', '7195550102');
    $vehicle = attentionVehicle($customer, 2019, 'Toyota', 'Camry');
    attentionRepairOrder(RepairOrderStatus::InProgress, $vehicle, $customer, [
        'assigned_technician_id' => $tech->id,
        'opened_at' => now()->subDays(2),
        'repair_order_id' => 1801,
    ]);

    $ids = collect(app(StationAttentionProjection::class)->payload()['rows'])->pluck('repair_order_id');

    expect($ids)->not->toContain(1801);
});

test('old waiting-parts and unassigned jobs qualify', function (): void {
    $customer = attentionCustomer('Parts', 'Hold', '7195550103');
    $old = attentionRepairOrder(
        RepairOrderStatus::WaitingParts,
        attentionVehicle($customer, 2003, 'Subaru', 'Outback'),
        $customer,
        ['opened_at' => now()->subDays(120), 'repair_order_id' => 1457],
    );
    $old->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

    $unassigned = attentionRepairOrder(
        RepairOrderStatus::InProgress,
        attentionVehicle($customer, 2016, 'Honda', 'Civic'),
        $customer,
        ['repair_order_id' => 1713],
    );

    $rows = collect(app(StationAttentionProjection::class)->payload()['rows'])->keyBy('repair_order_id');

    expect($rows[1457]['attention_reasons'])->toContain('old_waiting_parts')
        ->and($rows[1713]['attention_reasons'])->toContain('unassigned')
        ->and($rows[1713]['assigned_technician'])->toBeNull();
});

test('ordinary waiting-parts that is not old is omitted unless another reason applies', function (): void {
    $tech = User::factory()->create(['name' => 'Alex Rivera']);
    $customer = attentionCustomer('Fresh', 'Parts', '7195550104');
    attentionRepairOrder(
        RepairOrderStatus::WaitingParts,
        attentionVehicle($customer, 2020, 'Ford', 'F-150'),
        $customer,
        [
            'assigned_technician_id' => $tech->id,
            'opened_at' => now()->subDays(3),
            'repair_order_id' => 1888,
        ],
    );

    $ids = collect(app(StationAttentionProjection::class)->payload()['rows'])->pluck('repair_order_id');

    expect($ids)->not->toContain(1888);
});

test('coming in today is projected beside attention and can mark a waiting-parts return', function (): void {
    $customer = attentionCustomer('Return', 'Visit', '7195550105');
    $vehicle = attentionVehicle($customer, 2017, 'Jeep', 'Cherokee');
    $repairOrder = attentionRepairOrder(RepairOrderStatus::WaitingParts, $vehicle, $customer, [
        'opened_at' => now()->subDays(40),
        'repair_order_id' => 1460,
    ]);

    $advisor = User::factory()->create();
    Appointment::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-08-23 13:00:00', 'America/Denver')->utc(),
        'ends_at' => Carbon::parse('2026-08-23 14:00:00', 'America/Denver')->utc(),
        'status' => AppointmentStatus::Scheduled,
        'kind' => AppointmentKind::Return,
        'concern' => 'Parts return',
    ]);

    $payload = app(StationAttentionProjection::class)->payload();
    $row = collect($payload['rows'])->firstWhere('repair_order_id', 1460);

    expect($payload['coming_in'])->not->toBeEmpty()
        ->and($payload['coming_in'][0]['kind'])->toBe('return')
        ->and($row['attention_reasons'])->toContain('return_scheduled')
        ->and($row['attention_reasons'])->toContain('appointment_today');
});

test('attention payload omits contact PII', function (): void {
    $customer = attentionCustomer('Secret', 'Customer', '7195559999');
    attentionRepairOrder(
        RepairOrderStatus::WaitingApproval,
        attentionVehicle($customer, 2011, 'Honda', 'Accord'),
        $customer,
        ['repair_order_id' => 1500],
    );

    $json = json_encode(app(StationAttentionProjection::class)->payload());

    expect($json)->not->toContain('7195559999')
        ->and($json)->not->toContain('secret@hidden.test')
        ->and($json)->toContain('Secret Customer');
});

test('station dashboard serves attention rows and coming in without dragon', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $customer = attentionCustomer('Dana', 'Miles', '7195550101');
    $repairOrder = attentionRepairOrder(
        RepairOrderStatus::WaitingApproval,
        attentionVehicle($customer, 2006, 'Chevrolet', 'Silverado'),
        $customer,
        ['repair_order_id' => 1597, 'opened_at' => now()->subDays(31)],
    );
    attentionRecommendedLabor($repairOrder, 184200);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk()
        ->assertJsonPath('today.attention.0.repair_order_id', 1597)
        ->assertJsonPath('today.attention.0.waiting_approval_amount_cents', 184200)
        ->assertJsonPath('today.summary.waiting_approval_amount_cents', 184200)
        ->assertJsonMissing(['7195550101'])
        ->assertJsonPath('dragon.ready', false);
});

test('hosted attention nudge uses snapshot only and failure does not break dashboard', function (): void {
    $issued = StationDeviceToken::issue('front-counter-glass', 'test.demo-auto.local');
    $customer = attentionCustomer('Dana', 'Miles', '7195550101');
    attentionRepairOrder(
        RepairOrderStatus::WaitingApproval,
        attentionVehicle($customer, 2006, 'Chevrolet', 'Silverado'),
        $customer,
        ['repair_order_id' => 1597],
    );

    $fake = app(FakeDragonProvider::class);
    $fake->script = [
        new DragonModelTurn('Start with RO 1597. Waiting approval is the board pressure.', []),
    ];

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/attention-nudge')
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('text', 'Start with RO 1597. Waiting approval is the board pressure.')
        ->assertJsonPath('model', 'fake-dragon');

    expect($fake->receivedMessages[0]['tools'] ?? [])->toBe([]);

    $fake->unavailable = true;
    cache()->flush();

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/attention-nudge')
        ->assertOk()
        ->assertJsonPath('available', false);

    $this->withToken($issued['plain_text'])
        ->getJson('/api/station/dashboard')
        ->assertOk()
        ->assertJsonPath('today.attention.0.repair_order_id', 1597);
});
