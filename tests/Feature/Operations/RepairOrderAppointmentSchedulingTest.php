<?php

use App\Ark\Dragon\Agent\DragonToolRegistry;
use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentKind;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\ArrivalPostureProjection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Station\StationDashboardProjection;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
    ]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

/**
 * @return array{0: RepairOrder, 1: Customer, 2: Vehicle}
 */
function scheduleTestRepairOrder(RepairOrderStatus $status = RepairOrderStatus::WaitingParts): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Return',
        'last_name' => 'Visit',
        'phone' => '7195552211',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'repair_order_id' => random_int(1400, 1499),
        'concern_summary' => 'Waiting on pads',
    ]);

    return [$repairOrder, $customer, $vehicle];
}

test('waiting parts RO can be scheduled without changing workflow status', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-23 15:00:00', 'UTC'));
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder] = scheduleTestRepairOrder();
    $openBefore = RepairOrder::query()->where('status', RepairOrderStatus::WaitingParts)->count();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.appointments.store', $repairOrder), [
            'starts_at' => '2026-08-26T08:00',
            'duration_minutes' => 60,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder));

    $repairOrder->refresh();
    $appointment = Appointment::query()->where('repair_order_id', $repairOrder->id)->first();

    expect($repairOrder->status->value)->toBe(RepairOrderStatus::WaitingParts->value)
        ->and($appointment)->not->toBeNull()
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->kind)->toBe(AppointmentKind::Return)
        ->and(ShopDisplayTimezone::present($appointment->starts_at)->format('Y-m-d H:i'))->toBe('2026-08-26 08:00')
        ->and(RepairOrder::query()->where('status', RepairOrderStatus::WaitingParts)->count())->toBe($openBefore);

    $posture = app(ArrivalPostureProjection::class)->forRepairOrder($repairOrder);
    expect($posture->present)->toBeTrue()
        ->and($posture->headline)->toBe('Scheduled')
        ->and($posture->whenLabel)->toContain('8:00');

    expect(OperationalEvent::query()->where('event_name', OperationalEventName::AppointmentScheduled->value)->count())
        ->toBeGreaterThan(0);

    Carbon::setTestNow();
});

test('reschedule keeps a single upcoming appointment and records previous time', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder] = scheduleTestRepairOrder();

    $this->actingAs($advisor)->post(route('operations.repair-orders.appointments.store', $repairOrder), [
        'starts_at' => '2026-08-26T08:00',
    ]);
    $this->actingAs($advisor)->post(route('operations.repair-orders.appointments.store', $repairOrder), [
        'starts_at' => '2026-08-27T09:30',
    ]);

    $upcoming = Appointment::query()
        ->where('repair_order_id', $repairOrder->id)
        ->whereIn('status', ['scheduled', 'confirmed'])
        ->get();

    expect($upcoming)->toHaveCount(1)
        ->and(ShopDisplayTimezone::present($upcoming->first()->starts_at)->format('Y-m-d H:i'))->toBe('2026-08-27 09:30');

    expect(OperationalEvent::query()->where('event_name', OperationalEventName::AppointmentRescheduled->value)->exists())->toBeTrue();
    expect($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::WaitingParts->value);
});

test('cancel and no-show leave the repair order intact', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder] = scheduleTestRepairOrder(RepairOrderStatus::InProgress);

    $this->actingAs($advisor)->post(route('operations.repair-orders.appointments.store', $repairOrder), [
        'starts_at' => '2026-08-26T08:00',
    ]);
    $appointment = Appointment::query()->where('repair_order_id', $repairOrder->id)->first();

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $appointment), [
            'status' => AppointmentStatus::Canceled->value,
        ])
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Canceled)
        ->and($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::InProgress->value);

    $this->actingAs($advisor)->post(route('operations.repair-orders.appointments.store', $repairOrder), [
        'starts_at' => '2026-08-28T10:00',
    ]);
    $second = Appointment::query()->where('repair_order_id', $repairOrder->id)->where('status', 'scheduled')->first();

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $second), [
            'status' => AppointmentStatus::NoShow->value,
        ])
        ->assertRedirect();

    expect($second->fresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and($second->fresh()->no_show_at)->not->toBeNull()
        ->and($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::InProgress->value)
        ->and(RepairOrder::query()->find($repairOrder->id))->not->toBeNull();
});

test('mark arrived does not rewrite waiting parts', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder] = scheduleTestRepairOrder();
    $this->actingAs($advisor)->post(route('operations.repair-orders.appointments.store', $repairOrder), [
        'starts_at' => '2026-08-26T08:00',
    ]);
    $appointment = Appointment::query()->where('repair_order_id', $repairOrder->id)->first();

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $appointment), [
            'status' => AppointmentStatus::Arrived->value,
        ]);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Arrived)
        ->and($appointment->fresh()->arrived_at)->not->toBeNull()
        ->and($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::WaitingParts->value);
});

test('dragon can read appointments and cannot write through the tool', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder] = scheduleTestRepairOrder();
    $this->actingAs($advisor)->post(route('operations.repair-orders.appointments.store', $repairOrder), [
        'starts_at' => ShopDisplayTimezone::now()->addDay()->format('Y-m-d').'T08:00',
    ]);

    $registry = app(DragonToolRegistry::class);
    $tool = $registry->get('appointments.query');
    $result = $tool->invoke([
        'window' => 'tomorrow',
        'waiting_parts_return' => true,
    ]);

    expect($result['read_only'])->toBeTrue()
        ->and($result['writes'])->toBeFalse()
        ->and($result['count'])->toBeGreaterThan(0);

    expect(fn () => $registry->get('appointments.schedule'))->toThrow(InvalidArgumentException::class);
});

test('shop glass coming_in payload works without dragon', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder] = scheduleTestRepairOrder();
    $this->actingAs($advisor)->post(route('operations.repair-orders.appointments.store', $repairOrder), [
        'starts_at' => ShopDisplayTimezone::now()->format('Y-m-d').'T08:00',
    ]);

    $payload = app(StationDashboardProjection::class)->payload();

    expect($payload['coming_in'] ?? null)->toBeArray()
        ->and($payload['coming_in'])->not->toBeEmpty()
        ->and($payload['coming_in'][0]['repair_order_status'])->toBe(RepairOrderStatus::WaitingParts->value)
        ->and($payload['coming_in'][0]['appointment_status'])->toBe(AppointmentStatus::Scheduled->value);
});

test('guests cannot schedule a repair order', function (): void {
    [$repairOrder] = scheduleTestRepairOrder();

    $this->post(route('operations.repair-orders.appointments.store', $repairOrder), [
        'starts_at' => '2026-08-26T08:00',
    ])->assertRedirect();
});
