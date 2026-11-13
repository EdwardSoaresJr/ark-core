<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    Http::fake();
});

test('known customer without an open visit shows Check In prefilling the customer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Known',
        'last_name' => 'Patron',
        'phone' => '7195556101',
    ]);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195556101',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Shop,
        'posture_changed_at' => now(),
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertSee('data-comms-context-check-in="check_in"', false)
        ->assertSee('data-comms-check-in', false)
        ->assertSee('Check In', false)
        ->getContent();

    expect($html)->toContain('customer_id='.$customer->id)
        ->and($html)->toContain(route('operations.intake.create', ['customer_id' => $customer->id], false));
});

test('unknown contact shows Check In prefilling the conversation phone', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195556102',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Shop,
        'posture_changed_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertSee('data-comms-context-check-in="check_in"', false)
        ->assertSee('Check In', false)
        ->assertSee('phone=7195556102', false);
});

test('open repair order is recognized and Check In becomes New visit', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Open',
        'last_name' => 'Visit',
        'phone' => '7195556103',
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
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brakes',
    ]);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195556103',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Shop,
        'posture_changed_at' => now(),
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertSee('data-open-ro="RO '.$repairOrder->repair_order_id.'"', false)
        ->assertSee('View repair order', false)
        ->assertSee('Waiting Approval', false)
        ->assertSee('data-comms-context-check-in="new_visit"', false)
        ->assertSee('New visit', false)
        ->getContent();

    expect($html)->toContain('customer_id='.$customer->id)
        ->and($html)->toContain('This visit already has an open repair order')
        ->and($html)->not->toContain('data-comms-context-check-in="check_in"');
});

test('staff without repair-order manage cannot see Check In in the context rail', function (): void {
    $viewer = User::factory()->create()->givePermissionTo([
        ArkCapability::OperationsAccess->value,
        'customers.manage',
        'repair_orders.view',
    ]);
    expect($viewer->can(ArkCapability::RepairOrdersManage->value))->toBeFalse();

    Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'Manage',
        'phone' => '7195556104',
    ]);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195556104',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Shop,
        'posture_changed_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertDontSee('data-comms-check-in', false)
        ->assertDontSee('data-comms-context-check-in', false);
});

test('lead concern and arrived appointment prefill Check In without treating RO status as check-in', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Arrived',
        'last_name' => 'Guest',
        'phone' => '7195556105',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'contact_name' => 'Arrived Guest',
        'contact_phone' => '7195556105',
        'customer_id' => $customer->id,
        'concern' => 'Rattle under load',
    ]);
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'lead_id' => $lead->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addHour(),
        'concern' => 'Rattle under load',
        'status' => AppointmentStatus::Arrived,
    ]);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195556105',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Shop,
        'posture_changed_at' => now(),
    ]);
    $lead->forceFill(['conversation_id' => $conversation->id])->save();

    $html = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'conversation' => $conversation->id,
        ]))
        ->assertOk()
        ->assertSee('data-comms-context-check-in="check_in"', false)
        ->assertSee('Customer arrived. Check In opens the repair order for this visit.', false)
        ->getContent();

    expect($html)->toContain('appointment_id='.$appointment->id)
        ->and($html)->toContain('vehicle_id='.$vehicle->id);
});
