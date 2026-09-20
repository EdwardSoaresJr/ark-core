<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Encounters\Encounter;
use App\Ark\Operations\Encounters\EncounterOperationalState;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
});

test('intake prefills concern from query string on open step', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'phone' => '7195550199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $this->get(route('operations.intake.create', [
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'concern' => 'Brake noise when stopping.',
    ]))
        ->assertRedirect();

    $this->followingRedirects()
        ->get(route('operations.intake.create', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concern' => 'Brake noise when stopping.',
        ]))
        ->assertOk()
        ->assertSee('Brake noise when stopping.');
});

test('advisor home no longer surfaces open encounter cards', function () {
    Encounter::query()->create([
        'concern' => 'Should not appear on workboard.',
        'callback_phone' => '7195550100',
        'source' => EncounterSource::Phone->value,
        'operational_state' => EncounterOperationalState::New,
        'created_by' => auth()->id(),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Should not appear on workboard.')
        ->assertDontSee('Unresolved vehicle');
});

test('website lead intake route records conversation and redirects with context', function () {
    $this->post(route('operations.intake.leads.store'), [
        'concern' => 'Need oil change and tire rotation this week.',
        'callback_name' => 'Jordan Lee',
        'callback_phone' => '555-0199',
        'rough_vehicle' => '2019 Subaru Outback',
        'source' => EncounterSource::Website->value,
    ])
        ->assertRedirect();

    $lead = \App\Ark\Operations\Leads\Lead::query()->first();

    expect($lead)->not->toBeNull();

    $this->followingRedirects()
        ->get(route('operations.intake.create', [
            'lead_id' => $lead->id,
            'phone' => '5550199',
            'concern' => 'Need oil change and tire rotation this week.',
            'q' => 'Jordan Lee',
        ]))
        ->assertOk();
});

test('inbound phone message builds intake entry params with concern', function () {
    expect(\App\Ark\Operations\Intake\IntakeEntryQuery::fromInboundPhoneMessage(
        '7195557777',
        'My Jeep overheats',
    ))->toBe([
        'phone' => '7195557777',
        'concern' => 'My Jeep overheats',
    ]);
});
