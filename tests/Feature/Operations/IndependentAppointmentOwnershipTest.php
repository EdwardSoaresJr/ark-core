<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentBookingIdentity;
use App\Ark\Operations\Appointments\AppointmentScheduleRowPresenter;
use App\Ark\Operations\Appointments\AppointmentSmsCopy;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\AppointmentsBoardProjection;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'shop_name' => 'Demo Auto Repair',
        'phone' => '7194136227',
        'telephony_inbound_number' => '7195559999',
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
        'appointment_slot_minutes' => 30,
    ]);
    ShopSettings::forgetCurrent();
});

function independentAppointmentBay(): Workstation
{
    return Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);
}

test('schema supports independent appointment ownership', function () {
    expect(Schema::hasColumn('appointments', 'contact_name'))->toBeTrue()
        ->and(Schema::hasColumn('appointments', 'contact_phone'))->toBeTrue()
        ->and(Schema::hasColumn('appointments', 'contact_email'))->toBeTrue()
        ->and(Schema::hasColumn('appointments', 'lead_id'))->toBeTrue();
});

test('can create appointment with name and phone and no customer', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    independentAppointmentBay();

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'contact_name' => 'Avairee Foote',
            'contact_phone' => '7195550101',
            'contact_email' => 'avairee@example.test',
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-09-11T13:00',
            'ends_at' => '2026-09-11T14:00',
            'concern' => 'Engine loses power with A/C on',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->customer_id)->toBeNull()
        ->and($appointment->repair_order_id)->toBeNull()
        ->and($appointment->vehicle_id)->toBeNull()
        ->and($appointment->lead_id)->toBeNull()
        ->and($appointment->contact_name)->toBe('Avairee Foote')
        ->and($appointment->contact_phone)->not->toBeNull()
        ->and($appointment->concern)->toBe('Engine loses power with A/C on');

    Carbon::setTestNow();
});

test('unlinked appointment without sufficient contact identity is rejected', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    independentAppointmentBay();

    $this->actingAs($advisor)
        ->from(route('operations.schedule', ['mode' => 'new']))
        ->post(route('operations.appointments.store'), [
            'contact_name' => 'No Phone',
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-09-11T13:00',
            'ends_at' => '2026-09-11T14:00',
            'concern' => 'Noise',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['contact_name', 'contact_phone']);

    expect(Appointment::query()->count())->toBe(0);

    Carbon::setTestNow();
});

test('existing customer appointment still works and snapshots contact', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    independentAppointmentBay();
    $customer = Customer::query()->create([
        'first_name' => 'Hunter',
        'last_name' => 'Bell',
        'phone' => '5550100',
        'email' => 'hunter@example.test',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-09-11T09:00',
            'ends_at' => '2026-09-11T10:00',
            'concern' => 'Oil change',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->customer_id)->toBe($customer->id)
        ->and($appointment->contact_name)->toBe('Hunter Bell')
        ->and($appointment->contact_phone)->not->toBeNull();

    Carbon::setTestNow();
});

test('repair order schedule path still works', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    independentAppointmentBay();
    $customer = Customer::query()->create([
        'first_name' => 'RO',
        'last_name' => 'Customer',
        'phone' => '5550200',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Chevrolet',
        'model' => 'Malibu',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'repair_order_id' => 9001,
        'concern_summary' => 'Follow-up diagnosis',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'repair_order_id' => $repairOrder->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-09-11T10:00',
            'ends_at' => '2026-09-11T11:00',
            'concern' => 'Follow-up diagnosis',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->repair_order_id)->toBe($repairOrder->id)
        ->and($appointment->customer_id)->toBe($customer->id)
        ->and($appointment->vehicle_id)->toBe($vehicle->id);

    Carbon::setTestNow();
});

test('lead schedule prefills contact and concern and saves lead_id', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    independentAppointmentBay();

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Engine loses power with A/C on',
        'contact_name' => 'Avairee Foote',
        'contact_phone' => '7195550101',
        'contact_email' => 'avairee@example.test',
        'contact_preference' => LeadContactPreference::Text,
        'vehicle_year' => 2018,
        'vehicle_make' => 'Chevrolet',
        'vehicle_model' => 'Malibu',
        'metadata' => [
            'preferred_period' => 'afternoon',
            'preferred_date' => '2026-09-11',
        ],
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.schedule', ['lead' => $lead->id]))
        ->assertOk()
        ->assertSee('Avairee Foote', false)
        ->assertSee('7195550101', false)
        ->assertSee('Engine loses power with A/C on', false)
        ->assertSee('afternoon', false)
        ->assertSee('2018 Chevrolet Malibu', false);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'lead_id' => $lead->id,
            'contact_name' => 'Avairee Foote',
            'contact_phone' => '7195550101',
            'contact_email' => 'avairee@example.test',
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-09-11T13:00',
            'ends_at' => '2026-09-11T14:00',
            'concern' => 'Engine loses power with A/C on',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->lead_id)->toBe($lead->id)
        ->and($appointment->customer_id)->toBeNull()
        ->and($appointment->contact_name)->toBe('Avairee Foote');

    Carbon::setTestNow();
});

test('conversation schedule prefills from lead without requiring customer', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195550101',
        'status' => ConversationStatus::Open,
    ]);

    Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'AC power loss',
        'contact_name' => 'Avairee Foote',
        'contact_phone' => '7195550101',
        'contact_preference' => LeadContactPreference::Text,
        'conversation_id' => $conversation->id,
        'metadata' => ['preferred_period' => 'afternoon'],
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.schedule', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Avairee Foote', false)
        ->assertDontSee('Identify the customer first', false);
});

test('contact snapshots persist after customer is later linked', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();

    $appointment = Appointment::query()->create([
        'customer_id' => null,
        'contact_name' => 'Booked Name',
        'contact_phone' => '7195550101',
        'contact_email' => 'booked@example.test',
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'Brakes',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Later',
        'last_name' => 'Linked',
        'phone' => '7195559999',
        'email' => 'later@example.test',
    ]);

    $appointment->forceFill(['customer_id' => $customer->id])->save();
    $appointment->refresh();

    expect($appointment->contact_name)->toBe('Booked Name')
        ->and($appointment->contact_phone)->toBe('7195550101')
        ->and($appointment->contact_email)->toBe('booked@example.test')
        ->and(AppointmentBookingIdentity::displayName($appointment))->toBe('Later Linked')
        ->and(AppointmentBookingIdentity::displayPhone($appointment))->toContain('7195559999');
});

test('customer deletion nulls customer_id and keeps appointment', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Delete',
        'last_name' => 'Me',
        'phone' => '5550300',
    ]);
    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'contact_name' => 'Delete Me',
        'contact_phone' => '5550300',
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'History',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $customer->delete();

    $appointment->refresh();

    expect(Appointment::query()->whereKey($appointment->id)->exists())->toBeTrue()
        ->and($appointment->customer_id)->toBeNull()
        ->and($appointment->contact_name)->toBe('Delete Me')
        ->and($appointment->displayName())->toBe('Delete Me');
});

test('lead deletion nulls lead_id and keeps appointment', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Noise',
        'contact_name' => 'Lead Person',
        'contact_phone' => '5550400',
        'contact_preference' => LeadContactPreference::Text,
    ]);
    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();
    $appointment = Appointment::query()->create([
        'lead_id' => $lead->id,
        'contact_name' => 'Lead Person',
        'contact_phone' => '5550400',
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'Noise',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $lead->delete();
    $appointment->refresh();

    expect(Appointment::query()->whereKey($appointment->id)->exists())->toBeTrue()
        ->and($appointment->lead_id)->toBeNull()
        ->and($appointment->contact_name)->toBe('Lead Person');
});

test('board calendar and show render unlinked appointments', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-09-11 08:00')->utc());
    $advisor = actingAsLearnCurrentAdvisor();
    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();

    $appointment = Appointment::query()->create([
        'customer_id' => null,
        'contact_name' => 'Avairee Foote',
        'contact_phone' => '7195550101',
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'Engine loses power with A/C on',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $board = app(AppointmentsBoardProjection::class)->comingInOn(ShopDisplayTimezone::now());
    expect(collect($board)->pluck('customer_label')->all())->toContain('Avairee Foote');

    $row = app(AppointmentScheduleRowPresenter::class)->present($appointment, $advisor);
    expect($row['customer_name'])->toBe('Avairee Foote')
        ->and($row['customer_url'])->toBe(route('operations.appointments.show', $appointment));

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['date' => '2026-09-11']))
        ->assertOk()
        ->assertSee('Avairee Foote', false);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.show', $appointment))
        ->assertOk()
        ->assertSee('Avairee Foote', false)
        ->assertSee('Not linked to a customer yet', false)
        ->assertSee('Engine loses power with A/C on', false)
        ->assertSee('Check In / Create RO', false);

    Carbon::setTestNow();
});

test('sms confirmation resolves snapshot phone without customer', function () {
    bindFakeOutboundSms();

    Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'America/Denver'));
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    PhoneSmsCapability::query()->create([
        'normalized_phone' => PhoneNumber::normalize('7195550101'),
        'valid' => true,
        'line_type' => 'mobile',
        'carrier_name' => 'Test',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
        'raw_payload' => ['source' => 'test'],
    ]);

    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();
    $appointment = Appointment::query()->create([
        'customer_id' => null,
        'contact_name' => 'Avairee Foote',
        'contact_phone' => '7195550101',
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'Engine loses power',
        'status' => AppointmentStatus::Scheduled,
    ]);

    expect(AppointmentSmsCopy::confirmation($appointment))->toContain('Engine loses power');

    $this->actingAs($advisor)
        ->post(route('operations.appointments.sms.confirmation', $appointment))
        ->assertRedirect(route('operations.appointments.show', $appointment));

    $message = ConversationMessage::query()->sole();

    expect($appointment->fresh()->confirmation_sms_sent_at)->not->toBeNull()
        ->and($message->body)->toContain("You're scheduled")
        ->and($message->conversation->contact_address)->toBe(PhoneNumber::normalize('7195550101'));

    Carbon::setTestNow();
});

test('brand-new caller confirmation creates conversation when none exists', function () {
    bindFakeOutboundSms();

    Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'America/Denver'));
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $normalized = PhoneNumber::normalize('7195557777');
    PhoneSmsCapability::query()->create([
        'normalized_phone' => $normalized,
        'valid' => true,
        'line_type' => 'mobile',
        'carrier_name' => 'Test',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
        'raw_payload' => ['source' => 'test'],
    ]);

    expect(Conversation::query()->where('contact_address', $normalized)->exists())->toBeFalse();

    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();
    $appointment = Appointment::query()->create([
        'customer_id' => null,
        'lead_id' => null,
        'contact_name' => 'New Caller',
        'contact_phone' => '7195557777',
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'Battery',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.sms.confirmation', $appointment))
        ->assertRedirect();

    $conversation = Conversation::query()->where('contact_address', $normalized)->sole();
    expect(ConversationMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(1)
        ->and($appointment->fresh()->confirmation_sms_sent_at)->not->toBeNull();

    Carbon::setTestNow();
});

test('staff can correct appointment booking phone without changing customer', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    independentAppointmentBay();
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Advisor',
        'phone' => '7195551231',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-09-11T09:00',
            'ends_at' => '2026-09-11T10:00',
            'concern' => 'Typo check',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();
    expect($appointment->contact_phone)->not->toBeNull();

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.update', $appointment), [
            'customer_id' => $customer->id,
            'contact_name' => 'Molly Advisor',
            'contact_phone' => '7195551213',
            'contact_email' => '',
            'starts_at' => '2026-09-11T09:00',
            'ends_at' => '2026-09-11T10:00',
            'concern' => 'Typo check',
            'advisor_user_id' => $advisor->id,
        ])
        ->assertRedirect();

    $appointment->refresh();
    $customer->refresh();

    expect($appointment->contact_phone)->toBe(PhoneNumber::normalize('7195551213') ?? '7195551213')
        ->and($customer->phone)->toBe('7195551231');

    Carbon::setTestNow();
});

test('customer phone change does not rewrite appointment booking snapshot', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Booked',
        'last_name' => 'As',
        'phone' => '7195551111',
    ]);
    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'contact_name' => 'Booked As',
        'contact_phone' => '7195551111',
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'No sync',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $customer->forceFill(['phone' => '7195559999'])->save();
    $appointment->refresh();

    expect($appointment->contact_phone)->toBe('7195551111');
});

test('legacy linked appointment backfills snapshots before customer null-on-delete', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Legacy',
        'last_name' => 'Row',
        'phone' => '7195554444',
        'email' => 'legacy@example.test',
    ]);
    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'contact_name' => 'Will Be Cleared',
        'contact_phone' => '0000000000',
        'contact_email' => 'clear@example.test',
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'History',
        'status' => AppointmentStatus::Scheduled,
    ]);

    // Simulate pre-migration / empty snapshot state after columns existed.
    DB::table('appointments')->where('id', $appointment->id)->update([
        'contact_name' => null,
        'contact_phone' => null,
        'contact_email' => null,
    ]);

    $migration = require database_path('migrations/2026_09_05_200000_make_appointments_customer_optional_with_contact_snapshots.php');
    $method = new \ReflectionMethod($migration, 'backfillSnapshotsFromCustomers');
    $method->setAccessible(true);
    $method->invoke($migration);

    $row = DB::table('appointments')->where('id', $appointment->id)->first();
    expect($row->contact_name)->toBe('Legacy Row')
        ->and($row->contact_phone)->toBe('7195554444')
        ->and($row->contact_email)->toBe('legacy@example.test');

    $customer->delete();
    $appointment->refresh();

    expect($appointment->customer_id)->toBeNull()
        ->and($appointment->displayName())->toBe('Legacy Row')
        ->and($appointment->displayPhone())->toBe('7195554444');
});

test('exact starts_at still required for unlinked appointment', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    independentAppointmentBay();

    $this->actingAs($advisor)
        ->from(route('operations.schedule', ['mode' => 'new']))
        ->post(route('operations.appointments.store'), [
            'contact_name' => 'Avairee Foote',
            'contact_phone' => '7195550101',
            'advisor_user_id' => $advisor->id,
            'starts_date' => '2026-09-11',
            'starts_time' => '',
            'duration_minutes' => 60,
            'concern' => 'Brakes',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect(Appointment::query()->count())->toBe(0);
});
