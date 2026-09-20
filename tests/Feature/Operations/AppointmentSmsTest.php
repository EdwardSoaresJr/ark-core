<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SendAppointmentReminderSmsAction;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
        
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'shop_name' => 'Demo Auto Repair',
        'phone' => '7194136227',
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();
});

function appointmentSmsCustomer(): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Patron',
        'phone' => '7195550199',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

    // Capability gate shipped after appointment SMS — seed known-mobile so Lookup is not required.
    PhoneSmsCapability::query()->create([
        'normalized_phone' => PhoneNumber::normalize((string) $customer->phone),
        'valid' => true,
        'line_type' => 'mobile',
        'carrier_name' => 'Test',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
        'raw_payload' => ['source' => 'test'],
    ]);

    return $customer;
}

function appointmentForSms(User $advisor, Customer $customer, Carbon $startsLocal): Appointment
{
    return Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal($startsLocal->format('Y-m-d H:i'))->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal($startsLocal->copy()->addHour()->format('Y-m-d H:i'))->utc(),
        'concern' => 'Brake noise',
        'status' => AppointmentStatus::Scheduled,
    ]);
}

test('scheduling an appointment flashes the customer text prompt', function () {
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = appointmentSmsCustomer();
    $bay = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'workstation_id' => $bay->id,
            'starts_at' => '2026-07-20T09:00',
            'ends_at' => '2026-07-20T10:00',
            'concern' => 'Oil change',
        ])
        ->assertRedirect()
        ->assertSessionHas('appointment_comms_prompt', true);

    $appointment = Appointment::query()->sole();

    $this->actingAs($advisor)
        ->get(route('operations.appointments.show', $appointment).'?comms=1')
        ->assertOk()
        ->assertSee('Send confirmation SMS', false)
        ->assertSee('Customer text — confirmation', false)
        ->assertSee('data-appointment-sms', false);
});

test('advisor can send appointment confirmation sms into conversation', function () {
    bindFakeOutboundSms();

    Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'America/Denver'));
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = appointmentSmsCustomer();
    $appointment = appointmentForSms($advisor, $customer, Carbon::parse('2026-07-20 09:00:00'));

    $this->actingAs($advisor)
        ->post(route('operations.appointments.sms.confirmation', $appointment))
        ->assertRedirect(route('operations.appointments.show', $appointment))
        ->assertSessionHas('status');

    $message = ConversationMessage::query()->sole();

    expect($appointment->fresh()->confirmation_sms_sent_at)->not->toBeNull()
        ->and($message->body)->toContain("You're scheduled")
        ->and($message->body)->toContain('1 - Confirm')
        ->and($message->body)->toContain('3 - Get Directions')
        ->and($message->body)->toContain('STOP')
        ->and($message->metadata['message_action'] ?? null)->toBe('appointment_confirmation')
        ->and($message->metadata['appointment_id'] ?? null)->toBe($appointment->id);

    Carbon::setTestNow();
});

test('advisor can opt into day-before and hours-before reminders', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = appointmentSmsCustomer();
    $appointment = appointmentForSms($advisor, $customer, Carbon::parse('2026-07-20 09:00:00'));

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.sms.reminders', $appointment), [
            'reminder_day_before' => '1',
            'reminder_hours_before' => '1',
        ])
        ->assertRedirect(route('operations.appointments.show', $appointment));

    expect($appointment->fresh()->reminder_day_before)->toBeTrue()
        ->and($appointment->fresh()->reminder_hours_before)->toBe(1);
});

test('reminder command sends day-before and hours-before texts when due', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMapptremind01',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = appointmentSmsCustomer();
    $starts = Carbon::parse('2026-07-20 09:00:00');
    $appointment = appointmentForSms($advisor, $customer, $starts);
    $appointment->forceFill([
        'reminder_day_before' => true,
        'reminder_hours_before' => 1,
    ])->save();

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-19 09:00')->utc());
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect($appointment->fresh()->reminder_day_before_sent_at)->not->toBeNull()
        ->and(ConversationMessage::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->sole()->body)->toContain('tomorrow')
        ->and(ConversationMessage::query()->sole()->body)->toContain('1 - Confirm')
        ->and(ConversationMessage::query()->sole()->metadata['message_action'] ?? null)->toBe('appointment_reminder');

    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-07-20 08:00')->utc());
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect($appointment->fresh()->reminder_hours_before_sent_at)->not->toBeNull()
        ->and(ConversationMessage::query()->count())->toBe(2);

    Carbon::setTestNow();
});

test('reminder due helpers respect windows', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = appointmentSmsCustomer();
    $appointment = appointmentForSms($advisor, $customer, Carbon::parse('2026-07-20 09:00:00'));
    $appointment->forceFill([
        'reminder_day_before' => true,
        'reminder_hours_before' => 1,
    ])->save();

    $send = app(SendAppointmentReminderSmsAction::class);

    expect($send->isDayBeforeDue($appointment, ShopDisplayTimezone::parseLocal('2026-07-19 09:15')))->toBeTrue()
        ->and($send->isDayBeforeDue($appointment, ShopDisplayTimezone::parseLocal('2026-07-18 09:15')))->toBeFalse()
        ->and($send->isHoursBeforeDue($appointment, ShopDisplayTimezone::parseLocal('2026-07-20 08:10')))->toBeTrue()
        ->and($send->isHoursBeforeDue($appointment, ShopDisplayTimezone::parseLocal('2026-07-20 06:00')))->toBeFalse();
});
