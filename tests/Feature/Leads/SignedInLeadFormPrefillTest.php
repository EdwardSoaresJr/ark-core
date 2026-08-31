<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\PublicLeadFormContactPrefill;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('public_lead.phone_verification_required', false);
});

test('signed-in customer contact prefill projects identity fields', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550142',
        'email' => 'edward@example.test',
        'contact_preference' => LeadContactPreference::Call,
        'customer_type' => 'Retail',
    ]);

    $this->actingAs($customer, 'portal');

    $prefill = app(PublicLeadFormContactPrefill::class)->forCurrentCustomer();

    expect($prefill['signed_in'])->toBeTrue()
        ->and($prefill['first_name'])->toBe('Edward')
        ->and($prefill['last_name'])->toBe('Soares')
        ->and($prefill['phone'])->toBe(PhoneNumber::display('7195550142'))
        ->and($prefill['email'])->toBe('edward@example.test')
        ->and($prefill['contact_preference'])->toBe(LeadContactPreference::Call->value)
        ->and($prefill['skips_phone_verification'])->toBeTrue();
});

test('guest contact prefill is empty', function (): void {
    $prefill = app(PublicLeadFormContactPrefill::class)->forCurrentCustomer();

    expect($prefill['signed_in'])->toBeFalse()
        ->and($prefill['first_name'])->toBe('')
        ->and($prefill['phone'])->toBe('')
        ->and($prefill['skips_phone_verification'])->toBeFalse();
});

test('book page lead form prefills signed-in customer contact fields', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550142',
        'email' => 'edward@example.test',
        'customer_type' => 'Retail',
    ]);

    $this->actingAs($customer, 'portal')
        ->get(route('public.book', ['schedule' => 1, 'intent' => 'Schedule Service']))
        ->assertOk()
        ->assertSee('Still the best way to reach you?', false)
        ->assertSee('Alex Rivera', false)
        ->assertSee('edward@example.test', false)
        ->assertDontSee('Tell us a little about yourself', false);
});

test('signed-in customer can submit lead without phone verification when phone matches', function (): void {
    config()->set('public_lead.phone_verification_required', true);

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();

    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550142',
        'email' => 'edward@example.test',
        'customer_type' => 'Retail',
    ]);

    $this->actingAs($customer, 'portal')
        ->post(route('public.leads.store'), [
            'concern' => 'U-joint noise on the highway.',
            'phone' => '719-555-0142',
            'first_name' => 'Edward',
            'last_name' => 'Soares',
            'email' => 'edward@example.test',
            'form_rendered_at' => now()->subSeconds(10)->timestamp,
        ])
        ->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->state)->toBe(LeadState::Received)
        ->and($lead->contact_phone)->toBe('7195550142')
        ->and($lead->customer_id)->toBe($customer->id)
        ->and($lead->concern)->toBe('U-joint noise on the highway.');
});

test('signed-in customer contact prefill includes vehicles on file', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550142',
        'email' => 'edward@example.test',
        'customer_type' => 'Retail',
    ]);

    $ram = $customer->vehicles()->create([
        'year' => 2016,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $this->actingAs($customer, 'portal');

    $prefill = app(PublicLeadFormContactPrefill::class)->forCurrentCustomer();

    expect($prefill['vehicles'])->toHaveCount(1)
        ->and($prefill['vehicles'][0]['id'])->toBe($ram->id)
        ->and($prefill['vehicles'][0]['label'])->toBe('2016 Ram 2500')
        ->and($prefill['default_vehicle_selection'])->toBe((string) $ram->id);
});

test('homepage lead form lists signed-in customer vehicles', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550142',
        'email' => 'edward@example.test',
        'customer_type' => 'Retail',
    ]);

    $customer->vehicles()->create([
        'year' => 2016,
        'make' => 'Ram',
        'model' => '2500',
    ]);
    $customer->vehicles()->create([
        'year' => 2001,
        'make' => 'Dodge',
        'model' => 'Ram 1500',
    ]);

    $this->actingAs($customer, 'portal')
        ->get(route('public.book'))
        ->assertOk()
        ->assertSee('Welcome back, Edward', false)
        ->assertSee('Which vehicle should we look at?', false)
        ->assertSee('2016 Ram 2500', false)
        ->assertSee('2001 Dodge Ram 1500', false)
        ->assertDontSee('+ Another Vehicle', false)
        ->assertDontSee('Skip for now', false);
});

test('signed-in customer can attach an owned vehicle to a lead', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550142',
        'email' => 'edward@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = $customer->vehicles()->create([
        'year' => 2016,
        'make' => 'Ram',
        'model' => '2500',
        'vin' => '1C6RR7LT0GS123456',
    ]);

    $this->actingAs($customer, 'portal')
        ->post(route('public.leads.store'), [
            'concern' => 'Broken U-joint.',
            'phone' => '719-555-0142',
            'first_name' => 'Edward',
            'last_name' => 'Soares',
            'email' => 'edward@example.test',
            'vehicle_selection' => (string) $vehicle->id,
            'form_rendered_at' => now()->subSeconds(10)->timestamp,
        ])
        ->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->customer_id)->toBe($customer->id)
        ->and($lead->vehicle_id)->toBe($vehicle->id)
        ->and($lead->vehicle_year)->toBe(2016)
        ->and($lead->vehicle_make)->toBe('Ram')
        ->and($lead->vehicle_model)->toBe('2500')
        ->and($lead->vehicle_vin)->toBe('1C6RR7LT0GS123456');
});

test('signed-in customer cannot attach someone else vehicle', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550142',
        'customer_type' => 'Retail',
    ]);

    $other = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Owner',
        'phone' => '7195550199',
        'customer_type' => 'Retail',
    ]);

    $foreignVehicle = $other->vehicles()->create([
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Tacoma',
    ]);

    $this->actingAs($customer, 'portal')
        ->post(route('public.leads.store'), [
            'concern' => 'Trying to claim another vehicle.',
            'phone' => '719-555-0142',
            'first_name' => 'Edward',
            'last_name' => 'Soares',
            'vehicle_selection' => (string) $foreignVehicle->id,
            'form_rendered_at' => now()->subSeconds(10)->timestamp,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('vehicle_selection');

    expect(Lead::query()->count())->toBe(0);
});
