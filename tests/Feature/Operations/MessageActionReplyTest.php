<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\MessageActionContract;
use App\Ark\Operations\Messaging\MessageActionKey;
use App\Ark\Operations\Messaging\MessageActionReply;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');

    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'shop_name' => 'Demo Auto Repair',
        'phone' => '7194136227',
        'telephony_inbound_number' => '7195559999',
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Unit D',
        'city' => 'Demo City',
        'state' => 'CO',
        'postal_code' => '80909',
        'telephony_call_flow' => [
            'weekly_hours' => [
                'monday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
                'tuesday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
                'wednesday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
                'thursday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
                'friday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
                'saturday' => ['enabled' => false, 'open' => '09:00', 'close' => '13:00'],
                'sunday' => ['enabled' => false, 'open' => '09:00', 'close' => '13:00'],
            ],
        ],
        'message_actions' => [
            'tow_company' => "Pinky's Towing",
            'tow_phone' => '7195550100',
            'tow_notes' => 'Tell them Unit D.',
            'wifi_ssid' => 'LugsGuest',
            'wifi_password' => 'welcome123',
            'after_hours_pickup' => 'Keys in drop box.',
        ],
    ]);
    ShopSettings::forgetCurrent();
});

function messageActionCustomer(string $phone = '7195550199'): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Patron',
        'phone' => $phone,
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

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

function messageActionAppointment(User $advisor, Customer $customer): Appointment
{
    return Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-07-20 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-07-20 10:00')->utc(),
        'concern' => 'Brake noise',
        'status' => AppointmentStatus::Scheduled,
    ]);
}

function seedOpenReminderContract(User $advisor, Appointment $appointment): ConversationMessage
{
    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMcontract01', 'status' => 'queued'], 201),
    ]);

    test()->actingAs($advisor)
        ->post(route('operations.appointments.sms.confirmation', $appointment))
        ->assertRedirect();

    $message = ConversationMessage::query()->sole();

    // Ensure reply timestamp can land strictly after the contract outbound.
    $message->forceFill([])->refresh();
    ConversationMessage::query()->whereKey($message->id)->update([
        'occurred_at' => now()->subMinute(),
    ]);

    return $message->fresh();
}

test('reply 1 confirms appointment and does not leave shop turn', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = messageActionCustomer();
    $appointment = messageActionAppointment($advisor, $customer);
    $contract = seedOpenReminderContract($advisor, $appointment);

    expect($contract->metadata['message_action'] ?? null)->toBe(MessageActionKey::AppointmentConfirmation->value);

    config()->set('services.twilio.auth_token', null);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMreply0001',
        'From' => '+17195550199',
        'To' => '+17195559999',
        'Body' => '1',
        'NumMedia' => '0',
    ])->assertOk()
        ->assertSee('confirmed', false);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);

    $inbound = ConversationMessage::query()
        ->where('direction', OperationalCommunicationDirection::Inbound)
        ->sole();

    expect($inbound->metadata[MessageActionContract::META_REPLY] ?? null)->toBe(MessageActionReply::Confirm->value)
        ->and($inbound->conversation->fresh()->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and(MessageActionContract::isOpen($contract->fresh()))->toBeFalse();
});

test('reply 2 keeps shop attention for reschedule', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = messageActionCustomer('7195550200');
    $appointment = messageActionAppointment($advisor, $customer);
    seedOpenReminderContract($advisor, $appointment);

    config()->set('services.twilio.auth_token', null);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMreply0002',
        'From' => '+17195550200',
        'To' => '+17195559999',
        'Body' => '2',
        'NumMedia' => '0',
    ])->assertOk()
        ->assertSee('rescheduling', false);

    $inbound = ConversationMessage::query()
        ->where('direction', OperationalCommunicationDirection::Inbound)
        ->sole();

    expect($inbound->metadata[MessageActionContract::META_REPLY] ?? null)->toBe(MessageActionReply::Reschedule->value)
        ->and($inbound->conversation->fresh()->waiting_on)->toBe(ConversationWaitingOn::Shop);
});

test('reply 3 sends shop address automatically', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::sequence()
            ->push(['sid' => 'SMcontract03', 'status' => 'queued'], 201)
            ->push(['sid' => 'SMdirections', 'status' => 'queued'], 201),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = messageActionCustomer('7195550300');
    $appointment = messageActionAppointment($advisor, $customer);
    seedOpenReminderContract($advisor, $appointment);

    // Keep Twilio credentials for REST auto-reply; forge testing by clearing signature requirement.
    // Verifier allows missing token in testing — temporarily clear for ingress signature only,
    // then restore before ProcessMessageActionReplyAction directions send via same request…
    // Directions send runs inside the same request, so use TwiML fallback path instead:
    config()->set('services.twilio.auth_token', null);

    $response = $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMreply0003',
        'From' => '+17195550300',
        'To' => '+17195559999',
        'Body' => '3',
        'NumMedia' => '0',
    ]);

    $response->assertOk()
        ->assertSee('100 Main Street', false)
        ->assertSee('maps.google.com', false);

    $inbound = ConversationMessage::query()
        ->where('direction', OperationalCommunicationDirection::Inbound)
        ->sole();

    expect($inbound->metadata[MessageActionContract::META_REPLY] ?? null)->toBe(MessageActionReply::Directions->value);
});

test('send pickup tow wifi and hours message actions', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMaction01', 'status' => 'queued'], 201),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = messageActionCustomer('7195550400');

    foreach ([
        MessageActionKey::Pickup,
        MessageActionKey::Hours,
        MessageActionKey::Tow,
        MessageActionKey::Wifi,
    ] as $action) {
        $this->actingAs($advisor)
            ->postJson(route('operations.customers.conversation-actions.send', [
                'customer' => $customer,
                'messageAction' => $action->value,
            ]))
            ->assertOk()
            ->assertJsonPath('message_action', $action->value);
    }

    $bodies = ConversationMessage::query()->orderBy('id')->pluck('body');

    expect($bodies)->toHaveCount(4)
        ->and($bodies[0])->toContain('100 Main Street')
        ->and($bodies[0])->toContain('Hours:')
        ->and($bodies[1])->toContain('hours:')
        ->and($bodies[2])->toContain("Pinky's Towing")
        ->and($bodies[3])->toContain('LugsGuest');
});
