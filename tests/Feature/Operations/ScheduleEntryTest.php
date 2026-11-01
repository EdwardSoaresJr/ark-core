<?php

use App\Ark\Operations\Appointments\ScheduleUrl;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    ShopSettings::current()->update(['appointments_enabled' => true]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

test('schedule from customer prefills customer and skips search', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Lane',
        'phone' => '555-0201',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $this->actingAs($advisor)
        ->get(ScheduleUrl::to(['customer' => $customer->id]))
        ->assertOk()
        ->assertSee('Pat Lane', false)
        ->assertDontSee('Find an existing customer', false)
        ->assertSee('name="vehicle_id"', false)
        ->assertSee('value="'.$vehicle->id.'"', false);
});

test('schedule from customer with multiple vehicles does not auto-select a vehicle', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Multi',
        'last_name' => 'Fleet',
        'phone' => '555-0206',
    ]);
    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2022,
        'make' => 'Mazda',
        'model' => 'CX-5',
    ]);

    $context = app(\App\Ark\Operations\Appointments\ScheduleContextResolver::class)
        ->fromCustomer($customer->id);

    expect($context->customerId)->toBe($customer->id)
        ->and($context->vehicleId)->toBeNull()
        ->and($context->repairOrderId)->toBeNull();

    $this->actingAs($advisor)
        ->get(ScheduleUrl::to(['customer' => $customer->id]))
        ->assertOk()
        ->assertSee('Multi Fleet', false)
        ->assertSee('name="vehicle_id"', false)
        ->assertSee('Honda', false)
        ->assertSee('Mazda', false);
});

test('schedule from vehicle resolves customer and selects vehicle', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Vic',
        'last_name' => 'Owner',
        'phone' => '555-0202',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Toyota',
        'model' => 'Rav4',
    ]);

    $this->actingAs($advisor)
        ->get(ScheduleUrl::to(['vehicle' => $vehicle->id]))
        ->assertOk()
        ->assertSee('Vic Owner', false)
        ->assertSee('value="'.$vehicle->id.'"', false);

    $this->actingAs($advisor)
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Schedule', false)
        ->assertSee('/app/schedule?', false)
        ->assertSee('vehicle='.$vehicle->id, false)
        ->assertSee('customer='.$customer->id, false);
});

test('schedule from repair order prefills customer vehicle and concern', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Ro',
        'last_name' => 'Sched',
        'phone' => '555-0203',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Ford',
        'model' => 'Escape',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => 9901,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Come in Thursday',
    ]);

    $this->actingAs($advisor)
        ->get(ScheduleUrl::to(['repair_order' => $repairOrder->id]))
        ->assertOk()
        ->assertSee('Ro Sched', false)
        ->assertSee('Come in Thursday', false)
        ->assertSee('name="repair_order_id"', false);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Schedule Follow-up', false)
        ->assertSee('/app/schedule?repair_order='.$repairOrder->id, false);
});

test('schedule from conversation resolves linked customer and single vehicle', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Tex',
        'last_name' => 'Thread',
        'phone' => '555-0204',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '5550204',
        'status' => ConversationStatus::Open,
    ]);
    app(ConversationLinker::class)->link($conversation, $customer);

    $this->actingAs($advisor)
        ->get(ScheduleUrl::to(['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Tex Thread', false)
        ->assertSee((string) $vehicle->id, false)
        ->assertDontSee('Identify the customer first', false);
});

test('schedule from unlinked conversation prefills phone contact', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '5550299',
        'status' => ConversationStatus::Open,
    ]);

    $this->actingAs($advisor)
        ->get(ScheduleUrl::to(['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('name="contact_phone"', false)
        ->assertSee('5550299', false)
        ->assertDontSee('Identify the customer first', false);
});

test('legacy appointments create still works alongside schedule entry', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Legacy',
        'last_name' => 'Link',
        'phone' => '555-0205',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.create', ['customer_id' => $customer->id]))
        ->assertOk()
        ->assertSee('Legacy Link', false)
        ->assertSee('Appointment length', false)
        ->assertSee('Reserved labor', false)
        ->assertSee('· Auto', false)
        ->assertSee('Automatically matches appointment length unless you override it.', false)
        ->assertSee('Override', false)
        ->assertDontSee('Capacity details (optional)', false)
        ->assertDontSee('RO # (optional)', false)
        ->assertDontSee('Scheduled work', false);

    $this->actingAs($advisor)
        ->get(route('operations.schedule'))
        ->assertOk()
        ->assertSee('Find an existing customer', false);
});

test('schedule create renders on tabler in its search, new caller, and customer states', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Tabler',
        'last_name' => 'Booker',
        'phone' => '555-0207',
    ]);
    $legacyAncestor = 'ancestor-or-self::*[contains(concat(" ", normalize-space(@class), " "), " ark-legacy ")]';
    $xpathFor = function (string $url) use ($advisor): DOMXPath {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($this->actingAs($advisor)->get($url)->assertOk()->getContent());
        libxml_clear_errors();

        return new DOMXPath($document);
    };

    $search = $xpathFor(route('operations.schedule', ['q' => 'Booker']));
    $searchCard = $search->query('//*[@data-schedule-customer-search]')->item(0);

    expect(trim($search->query('//*[contains(@class, "page-title")]')->item(0)->textContent))->toBe('Schedule appointment')
        ->and($searchCard)->toBeInstanceOf(DOMElement::class)
        ->and($search->query($legacyAncestor, $searchCard)->length)->toBe(0)
        ->and($search->query('.//form[@method="GET"][@action="'.route('operations.schedule').'"]//input[@name="q"][@value="Booker"]', $searchCard)->length)->toBe(1)
        ->and($search->query('.//*[@data-schedule-matches]//a[contains(@href, "customer='.$customer->id.'")]', $searchCard)->length)->toBe(1);

    $noMatch = $xpathFor(route('operations.schedule', ['q' => 'Nobody Here']));

    expect($noMatch->query('//*[@data-schedule-no-match]//a[contains(@href, "mode=new")]')->length)->toBe(1)
        ->and($noMatch->query('//form[@action="'.route('operations.customers.store').'"]//input[@name="return_to"][@value="schedule"]')->length)->toBe(1);

    $newCaller = $xpathFor(route('operations.schedule', ['mode' => 'new']));
    $callerForm = $newCaller->query('//form[@data-schedule-form]')->item(0);

    expect($callerForm->getAttribute('action'))->toBe(route('operations.appointments.store'))
        ->and($newCaller->query('.//*[@data-schedule-caller]//input[@name="contact_name"][contains(@class, "form-control")]', $callerForm)->length)->toBe(1)
        ->and($newCaller->query('.//input[@name="customer_id"]', $callerForm)->length)->toBe(0);

    $booking = $xpathFor(ScheduleUrl::to(['customer' => $customer->id]));
    $form = $booking->query('//form[@data-schedule-form]')->item(0);

    expect($booking->query($legacyAncestor, $form)->length)->toBe(0)
        ->and($booking->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ark-legacy ")]', $form)->length)->toBe(0)
        ->and($booking->query('.//input[@name="customer_id"][@value="'.$customer->id.'"]', $form)->length)->toBe(1)
        ->and($booking->query('.//*[@data-schedule-customer]', $form)->item(0)->textContent)->toContain('Tabler Booker')
        ->and($booking->query('.//input[@name="starts_date"][@type="date"][contains(@class, "form-control")]', $form)->length)->toBe(1)
        ->and($booking->query('.//select[@name="starts_time"][contains(@class, "form-select")]', $form)->length)->toBe(1)
        ->and($booking->query('.//select[@name="duration_minutes"][contains(@class, "form-select")]', $form)->length)->toBe(1)
        ->and($booking->query('.//*[@data-appointment-reserved-labor]//button[normalize-space()="Override"]', $form)->length)->toBe(1)
        ->and($booking->query('.//select[@name="vehicle_id"][contains(@class, "form-select")]', $form)->length)->toBe(1)
        ->and($booking->query('.//select[@name="advisor_user_id"][contains(@class, "form-select")]', $form)->length)->toBe(1)
        ->and($booking->query('.//button[@type="submit"][contains(@class, "btn-primary")][normalize-space()="Schedule"]', $form)->length)->toBe(1);
});
