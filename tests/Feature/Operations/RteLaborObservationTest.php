<?php

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Tests\Support\RteLaborGuideFixtures;

test('rte recommendation applied records append-only observation facts', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');
    $repairOrder->vehicle()->update([
        'displacement_liters' => 5.7,
        'engine' => '5.7L HEMI',
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'BTJT',
            'lab_id' => '3461BTxx12199',
            'hours_basis' => 'avg',
            'include_add_ons' => 0,
            'apply_vehicle_age_padding' => 1,
            'apply_suggested' => 0,
        ])
        ->assertRedirect();

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::RteRecommendationApplied->value)
        ->where('aggregate_id', $repairOrder->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload_json['repair_order_id'])->toBe($repairOrder->id)
        ->and($event->payload_json['vehicle_match_confidence'])->toBeIn(['high', 'medium', 'low'])
        ->and($event->payload_json['tier'])->toBe('avg')
        ->and($event->payload_json['package_applied'])->toBeFalse()
        ->and($event->payload_json['final_hours'])->toBeGreaterThan(0)
        ->and($event->payload_json['line_ids'])->toHaveCount(1);
});

test('rte recommendation overridden records observation facts for rte labor lines', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');
    $repairOrder->vehicle()->update([
        'displacement_liters' => 5.7,
        'engine' => '5.7L HEMI',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'BTJT',
            'lab_id' => '3461BTxx12199',
            'hours_basis' => 'avg',
            'include_add_ons' => 0,
            'apply_vehicle_age_padding' => 0,
            'apply_suggested' => 0,
        ])
        ->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();
    $originalHours = (float) $line->quantity;

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
            'repair_order_concern_id' => $concern->id,
            'type' => $line->type->value,
            'description' => $line->description,
            'labor_entered_hours' => number_format($originalHours + 0.5, 2, '.', ''),
            'quantity' => number_format($originalHours + 0.5, 2, '.', ''),
            'labor_hours_overridden' => '1',
            'labor_override_reason' => 'Shop standard for this job.',
            'unit_price' => number_format($line->unit_price_cents / 100, 2, '.', ''),
        ])
        ->assertRedirect();

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::RteRecommendationOverridden->value)
        ->where('aggregate_id', $repairOrder->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload_json['line_id'])->toBe($line->id)
        ->and($event->payload_json['original_hours'])->toBe($originalHours)
        ->and($event->payload_json['overridden_hours'])->toBe(round($originalHours + 0.5, 2))
        ->and($event->payload_json['delta_hours'])->toBe(0.5)
        ->and($event->payload_json['vehicle_match_confidence'])->not->toBeNull();
});

test('operational events remain append-only for rte observation facts', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    RteLaborGuideFixtures::seedRam2500LaborRows();

    [$repairOrder, $concern] = createRteWorksheetRepairOrder(2010, 'Ram', '2500');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.repair-orders.rte-labor.apply', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'car_id_code' => 'BTJT',
            'lab_id' => '3461BTxx12199',
            'hours_basis' => 'avg',
            'include_add_ons' => 0,
            'apply_suggested' => 0,
        ]);

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::RteRecommendationApplied->value)
        ->firstOrFail();

    expect(fn () => $event->update(['payload_json' => ['tampered' => true]]))
        ->toThrow(LogicException::class);
});
