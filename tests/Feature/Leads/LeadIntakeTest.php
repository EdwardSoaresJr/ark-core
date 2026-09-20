<?php

use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('leads index redirects to communications needs attention', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.leads.index'))
        ->assertRedirect(\App\Ark\Operations\Communications\CommunicationsNeedsYou::url());
});

test('guest cannot view leads index', function (): void {
    $this->get(route('operations.leads.index'))
        ->assertRedirect();
});

test('advisor can mark lead contacted', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Oil change',
        'contact_phone' => '7195550102',
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->patch(route('operations.leads.state', $lead), ['state' => LeadState::Contacted->value])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->state)->toBe(LeadState::Contacted)
        ->and($lead->first_contacted_at)->not->toBeNull();
});

test('advisor can mark lead lost', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Wrong number',
        'contact_phone' => '7195550103',
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->patch(route('operations.leads.state', $lead), [
            'state' => LeadState::Lost->value,
            'lost_reason' => 'Not a fit',
        ])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->state)->toBe(LeadState::Lost)
        ->and($lead->lost_reason)->toBe('Not a fit')
        ->and($lead->lost_at)->not->toBeNull();
});

test('lead pressure counts new and not contacted', function (): void {
    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'One',
        'contact_phone' => '7195550104',
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Contacted,
        'concern' => 'Two',
        'contact_phone' => '7195550105',
        'first_contacted_at' => now(),
    ]);

    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $pressure = app(\App\Ark\Operations\Leads\LeadPressure::class)->resolve($user);

    expect($pressure['new_count'])->toBe(1)
        ->and($pressure['not_contacted_count'])->toBe(1)
        ->and($pressure['open_count'])->toBe(2);
});

test('website lead intake route creates lead and redirects with lead id', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($user)
        ->post(route('operations.intake.leads.store'), [
            'concern' => 'Need oil change and tire rotation this week.',
            'callback_name' => 'Jordan Lee',
            'callback_phone' => '555-0199',
            'rough_vehicle' => '2019 Subaru Outback',
            'source' => EncounterSource::Website->value,
        ])
        ->assertRedirect();

    $lead = Lead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->concern)->toContain('oil change');

    $response = $this->followingRedirects()->get(route('operations.intake.create', [
        'lead_id' => $lead->id,
    ]));

    $response->assertOk();
});

test('website lead with full name prefills intake customer step', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Rear brakes, shoes, turn drums — how much will this cost?',
        'contact_phone' => '7195550180',
        'contact_name' => 'Jeremiah Seress',
        'vehicle_year' => 2022,
        'vehicle_make' => 'Nissan',
        'vehicle_model' => 'Versa',
    ]);

    $user = actingAsLearnCurrentAdvisor();

    $this->actingAs($user)
        ->get(route('operations.leads.intake', $lead))
        ->assertRedirect();

    $this->actingAs($user)
        ->followingRedirects()
        ->get(route('operations.intake.create', ['lead_id' => $lead->id]))
        ->assertOk()
        ->assertSee('Jeremiah', false)
        ->assertSee('value="Jeremiah"', false)
        ->assertSee('value="Seress"', false);

    $this->actingAs($user)
        ->post(route('operations.customers.store'), [
            'intake' => 1,
            'lead_id' => $lead->id,
            'first_name' => 'Jeremiah',
            'last_name' => 'Seress',
            'phone' => '7195550180',
            'email' => 'jeremiah@example.com',
            'contact_preference' => LeadContactPreference::Text->value,
            'referral_source' => EncounterSource::Website->value,
            'customer_type' => 'Retail',
        ])
        ->assertRedirect();

    $customer = \App\Ark\Operations\Customers\Customer::query()->where('phone', '7195550180')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Jeremiah Seress')
        ->and($customer->last_name)->toBe('Seress');

    $this->actingAs($user)
        ->post(route('operations.customers.vehicles.store', $customer), [
            'intake' => 1,
            'lead_id' => $lead->id,
            'year' => 2022,
            'make' => 'Nissan',
            'model' => 'Versa',
        ])
        ->assertRedirect();

    $vehicle = $customer->vehicles()->first();

    expect($vehicle)->not->toBeNull()
        ->and($vehicle->year)->toBe(2022)
        ->and($vehicle->make)->toBe('Nissan')
        ->and($vehicle->model)->toBe('Versa');

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'lead_id' => $lead->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concerns' => [
                ['customer_states' => $lead->concern],
            ],
            'visit_mode' => 'drop_off',
            'billing_class' => 'Retail',
        ])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->not->toBeNull();
});

test('create contact from website lead accepts first name only', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes quote',
        'contact_phone' => '7195550181',
        'contact_name' => 'Jeremiah',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.leads.create-contact', $lead))
        ->assertOk()
        ->assertSee('Optional when the lead only gave a first name', false);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.leads.create-contact.store', $lead), [
            'first_name' => 'Jeremiah',
            'last_name' => '',
            'phone' => '7195550181',
            'referral_source' => EncounterSource::Website->value,
            'customer_type' => 'Retail',
        ])
        ->assertRedirect(\App\Ark\Operations\Communications\CommunicationsNeedsYou::url());

    $customer = \App\Ark\Operations\Customers\Customer::query()->where('phone', '7195550181')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Jeremiah');
});

test('opening ro from lead intake auto converts lead', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes grinding at low speed.',
        'contact_phone' => '7195550188',
        'contact_name' => 'Sam Rivera',
        'contact_email' => 'sam@example.com',
        'contact_preference' => LeadContactPreference::Call,
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Sam',
        'last_name' => 'Rivera',
        'phone' => '7195550188',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'CR-V',
    ]);

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'lead_id' => $lead->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concerns' => [
                ['customer_states' => 'Brakes grinding at low speed.'],
            ],
            'visit_mode' => 'drop_off',
            'billing_class' => 'Retail',
        ])
        ->assertRedirect();

    $lead->refresh();
    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->sole();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->toBe($repairOrder->id)
        ->and($lead->customer_id)->toBe($customer->id)
        ->and($lead->vehicle_id)->toBe($vehicle->id)
        ->and($lead->converted_at)->not->toBeNull();

    $customer->refresh();

    expect($customer->contact_preference)->toBe(LeadContactPreference::Call)
        ->and($customer->email)->toBe('sam@example.com');
});

test('intake without lead_id auto converts matching open lead', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Contacted,
        'concern' => 'Boos tube came off',
        'contact_phone' => '7197993060',
        'contact_name' => 'Mike',
        'vehicle_year' => 2017,
        'vehicle_make' => 'GMC',
        'vehicle_model' => 'Sierra 2500 HD',
        'first_contacted_at' => now()->subHour(),
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Mike',
        'last_name' => 'Driver',
        'phone' => '7197993060',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'GMC',
        'model' => 'Sierra 2500 HD',
    ]);

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concerns' => [
                ['customer_states' => 'Boos tube came off'],
            ],
            'visit_mode' => 'drop_off',
            'billing_class' => 'Retail',
        ])
        ->assertRedirect();

    $lead->refresh();
    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->sole();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->toBe($repairOrder->id);
});

test('customer hub draft RO auto converts matching open lead', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Overheating at idle.',
        'contact_phone' => '7195550199',
        'contact_name' => 'Jordan Lee',
        'vehicle_year' => 2015,
        'vehicle_make' => 'Toyota',
        'vehicle_model' => 'Camry',
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'phone' => '7195550199',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $this->actingAs($user)
        ->post(route('operations.customers.repair-orders.drafts.store', $customer), [
            'vehicle_id' => $vehicle->id,
            'visit_reason' => 'Overheating at idle.',
        ])
        ->assertRedirect();

    $lead->refresh();
    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->sole();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->toBe($repairOrder->id);
});

test('intake does not convert when multiple open leads share phone without disambiguation', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes grinding.',
        'contact_phone' => '7195550200',
        'vehicle_year' => 2010,
        'vehicle_make' => 'Ford',
        'vehicle_model' => 'F-150',
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'AC not cold.',
        'contact_phone' => '7195550200',
        'vehicle_year' => 2018,
        'vehicle_make' => 'Honda',
        'vehicle_model' => 'Accord',
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Shared',
        'phone' => '7195550200',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Chevrolet',
        'model' => 'Silverado',
    ]);

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concerns' => [
                ['customer_states' => 'Oil change due.'],
            ],
            'visit_mode' => 'drop_off',
            'billing_class' => 'Retail',
        ])
        ->assertRedirect();

    expect(Lead::query()->open()->count())->toBe(2);
});

test('reconcile open leads backfills existing repair order links', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Contacted,
        'concern' => 'Boos tube came off',
        'contact_phone' => '7197993060',
        'contact_name' => 'Mike',
        'vehicle_year' => 2017,
        'vehicle_make' => 'GMC',
        'vehicle_model' => 'Sierra 2500 HD',
        'first_contacted_at' => now()->subHours(12),
        'created_at' => now()->subHours(12),
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Mike',
        'last_name' => 'Driver',
        'phone' => '7197993060',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'GMC',
        'model' => 'Sierra 2500 HD',
    ]);

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::Draft,
        'concern_summary' => 'Boos tube came off',
        'opened_at' => now()->subHours(2),
    ]);

    app(\App\Ark\Operations\Intake\RepairOrderIntakeConcerns::class)->seed(
        $repairOrder,
        'Boos tube came off',
    );

    $linked = app(\App\Ark\Operations\Leads\LeadConverter::class)->reconcileOpenLeads();

    expect($linked)->toHaveCount(1)
        ->and($linked[0]['lead_id'])->toBe($lead->id)
        ->and($linked[0]['shop_repair_order_id'])->toBe($repairOrder->repair_order_id);

    $lead->refresh();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->toBe($repairOrder->id);
});
