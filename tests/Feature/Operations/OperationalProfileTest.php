<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Settings\ApplyOperationalProfileDefaults;
use App\Ark\Operations\Settings\OperationalProfile;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

it('applies repair shop profile defaults', function (): void {
    $settings = ShopSettings::current();
    $settings->update([
        'appointments_enabled' => false,
        'qz_printing_enabled' => false,
        'learn_training_gate_enabled' => false,
        'default_visit_mode' => RepairOrderVisitMode::WaitingHere->value,
    ]);

    $result = app(ApplyOperationalProfileDefaults::class)->apply(OperationalProfile::RepairShop);

    $settings->refresh();

    expect($result['profile'])->toBe(OperationalProfile::RepairShop)
        ->and($settings->operational_profile)->toBe('repair_shop')
        ->and($settings->appointments_enabled)->toBeTrue()
        ->and($settings->qz_printing_enabled)->toBeTrue()
        ->and($settings->learn_training_gate_enabled)->toBeFalse()
        ->and($settings->default_visit_mode)->toBe(RepairOrderVisitMode::DropOff->value);
});

it('applies mobile mechanic profile defaults', function (): void {
    app(ApplyOperationalProfileDefaults::class)->apply(OperationalProfile::MobileMechanic);

    $settings = ShopSettings::current()->fresh();

    expect($settings->operational_profile)->toBe('mobile_mechanic')
        ->and($settings->appointments_enabled)->toBeTrue()
        ->and($settings->qz_printing_enabled)->toBeFalse()
        ->and($settings->default_visit_mode)->toBe(RepairOrderVisitMode::OnSite->value);
});

it('opens an on-site repair order for mobile intake', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
    app(ApplyOperationalProfileDefaults::class)->apply(OperationalProfile::MobileMechanic);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $customer = Customer::query()->create([
        'first_name' => 'Casey',
        'last_name' => 'Roadside',
        'phone' => '555-0185',
        'customer_type' => 'Retail',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
        'plate' => 'A85MOB1',
    ]);

    $this->actingAs($admin)
        ->post(route('operations.intake.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'visit_reason' => 'Battery no-start at driveway',
            'visit_mode' => RepairOrderVisitMode::OnSite->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $repairOrder = RepairOrder::query()->sole();

    expect($repairOrder->on_site)->toBeTrue()
        ->and($repairOrder->waiting_here)->toBeFalse()
        ->and($repairOrder->drop_off)->toBeFalse()
        ->and(RepairOrderVisitMode::fromRepairOrder($repairOrder))->toBe(RepairOrderVisitMode::OnSite);
});

it('lets an admin apply a profile from settings', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->post(route('operations.settings.shop.operational-profile.apply'), [
            'operational_profile' => OperationalProfile::SoloShop->value,
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']));

    $settings = ShopSettings::current()->fresh();

    expect($settings->operational_profile)->toBe('solo_shop')
        ->and($settings->appointments_enabled)->toBeFalse()
        ->and($settings->learn_training_gate_enabled)->toBeFalse();
});
