<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Printing\KeyTagPrintGate;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('label printing chooses whether a key tag needs mileage in, and check in defaults points there', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $html = $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', ['section' => 'printing']))
        ->assertOk()
        ->assertSee('Key tag mileage required')
        ->assertSee('print without mileage in', false)
        ->assertSee("don't print until it is entered", false)
        ->assertSee('Whether a key tag needs mileage in before it prints:')
        ->assertSee("setActive('printing'); setPrintingTab('printers')", false)
        ->getContent();

    $formFor = function (string $action) use ($html): string {
        $start = strpos($html, 'action="'.$action.'"');
        expect($start)->not->toBeFalse();

        return substr($html, $start, strpos($html, '</form>', $start) - $start);
    };

    expect(substr_count($html, 'name="key_tag_mileage_requirement"'))->toBe(1)
        ->and($formFor(route('operations.settings.shop.printing.update')))->toContain('name="key_tag_mileage_requirement"')
        ->and($formFor(route('operations.settings.shop.workflow.update')))->not->toContain('name="key_tag_mileage_requirement"');

    $printing = [
        'qz_key_tag_vin_display' => 'last8',
        'oil_change_sticker_next_due_months' => '6',
        'oil_change_interval_miles' => '5000',
    ];

    $this->actingAs($admin)->patch(route('operations.settings.shop.printing.update'), [
        ...$printing,
        'key_tag_mileage_requirement' => KeyTagPrintGate::MILEAGE_IN,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(ShopSettings::current()->fresh()->key_tag_mileage_requirement)->toBe(KeyTagPrintGate::MILEAGE_IN);

    $this->actingAs($admin)->patch(route('operations.settings.shop.workflow.update'), [
        'default_visit_mode' => RepairOrderVisitMode::DropOff->value,
        'default_recommendation_intent' => RecommendationIntent::Maintenance->value,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(ShopSettings::current()->fresh()->key_tag_mileage_requirement)->toBe(KeyTagPrintGate::MILEAGE_IN);

    $this->actingAs($admin)->patch(route('operations.settings.shop.printing.update'), [
        ...$printing,
        'key_tag_mileage_requirement' => KeyTagPrintGate::NONE,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(ShopSettings::current()->fresh()->key_tag_mileage_requirement)->toBe(KeyTagPrintGate::NONE);

    $this->actingAs($admin)->patch(route('operations.settings.shop.workflow.update'), [
        'default_visit_mode' => RepairOrderVisitMode::DropOff->value,
        'key_tag_mileage_requirement' => KeyTagPrintGate::MILEAGE_IN,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(ShopSettings::current()->fresh()->key_tag_mileage_requirement)->toBe(KeyTagPrintGate::MILEAGE_IN);

    $this->actingAs($admin)->patch(route('operations.settings.shop.printing.update'), $printing)
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(ShopSettings::current()->fresh()->key_tag_mileage_requirement)->toBe(KeyTagPrintGate::MILEAGE_IN);
});

test('key tag print is blocked until mileage in when the shop requires it', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    ShopSettings::current()->update([
        'key_tag_mileage_requirement' => KeyTagPrintGate::MILEAGE_IN,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Key',
        'last_name' => 'Tag',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Key tag',
    ]);

    expect(KeyTagPrintGate::blockedReason($repairOrder))->toBe('Enter mileage in before printing the key tag.');

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.print-key-tag', $repairOrder))
        ->assertStatus(422);

    $repairOrder->update(['mileage_in' => 120000]);

    expect(KeyTagPrintGate::blockedReason($repairOrder->fresh()))->toBeNull();
});
