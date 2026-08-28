<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    ShopSettings::current()->update([
        'shop_name' => 'LugsNPlugs',
        'shop_timezone' => 'America/Denver',
        'learn_training_gate_enabled' => false,
    ]);
    ShopSettings::forgetCurrent();
});

test('recognition projection includes deferred concerns as still on our radar only', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
    ]);

    $order = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed->value,
        'closed_at' => now()->subDays(10),
        'concern_summary' => 'Inspection',
    ]);

    $deferred = $order->concerns()->create([
        'summary' => 'Rear pads near minimum',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'position' => 1,
    ]);
    $order->concerns()->create([
        'summary' => 'Customer declined wiper blades',
        'disposition' => RepairOrderConcernDisposition::Declined,
        'position' => 2,
    ]);
    $order->concerns()->create([
        'summary' => 'Oil change completed',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 3,
    ]);

    $home = app(CustomerRecognitionProjection::class)->forVehicle($customer, $vehicle);

    expect($home['vehicle']['label'])->toContain('Jeep')
        ->and($home['relationship']['still_on_radar'])->toHaveCount(1)
        ->and($home['relationship']['still_on_radar'][0]['concern_id'])->toBe($deferred->id)
        ->and($home['relationship']['still_on_radar'][0]['summary'])->toBe('Rear pads near minimum')
        ->and($home['relationship']['last_here_label'])->toContain('days ago')
        ->and($home['relationship']['last_service_label'])->toContain('Last visit');
});

test('recognized book flow is concierge first — not a form', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.recognition@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
    ]);

    $order = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed->value,
        'closed_at' => now()->subDays(3),
        'concern_summary' => 'Brakes',
    ]);
    $order->concerns()->create([
        'summary' => 'Replace rear pads next visit',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'position' => 1,
    ]);

    $this->actingAs($customer, 'portal')
        ->get(route('public.book'))
        ->assertOk()
        ->assertSee('Welcome back, Molly.', false)
        ->assertSee('2014 Jeep Wrangler', false)
        ->assertSee('Still on our radar', false)
        ->assertSee('Replace rear pads next visit', false)
        ->assertSee('What would you like to do?', false)
        ->assertSee('Schedule Service', false)
        ->assertSee('Oil Change', false)
        ->assertSee('While it’s here', false)
        ->assertDontSee('What can we help you with?', false)
        ->assertDontSee('How can we reach you?', false)
        ->assertDontSee('name="phone"', false);

    $this->actingAs($customer, 'portal')
        ->get(route('public.book', [
            'schedule' => 1,
            'vehicle' => $vehicle->id,
            'intent' => 'Oil Change',
            'radar' => [(string) $order->concerns()->first()->id],
        ]))
        ->assertOk()
        ->assertSee('When works best?', false)
        ->assertSee('Still the best way to reach you?', false)
        ->assertDontSee('What can we help you with?', false)
        ->assertDontSee('Which vehicle?', false)
        ->assertDontSee('Tell us a little more', false);
});

test('book phone continuation allows return to book after otp', function (): void {
    $this->get(route('portal.access', ['return' => '/book']))
        ->assertOk()
        ->assertSee('Continue with your phone', false)
        ->assertSee('We’ll look up your vehicle next', false)
        ->assertDontSee('My Account', false);

    expect(session(\App\Ark\Operations\Portal\PortalIntendedUrl::SESSION_KEY))->toBe('/book');
});
