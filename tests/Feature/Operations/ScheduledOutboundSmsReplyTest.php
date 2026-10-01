<?php

use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Communications\DispatchScheduledOutboundSmsReplyJob;
use App\Ark\Operations\Communications\ScheduledOutboundMessage;
use App\Ark\Operations\Communications\ScheduledOutboundMessageStatus;
use App\Ark\Operations\Communications\ScheduledOutboundMessageType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
            config()->set('app.timezone', 'UTC');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'shop_timezone' => 'America/Denver',
    ]);

    bindFakeOutboundSms();
});

test('tomorrow morning schedules sms reply without sending', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-22 04:30:00', 'UTC'));

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = scheduledOutboundSmsCustomer();

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'We will call you tomorrow with an update.',
            'timing' => 'tomorrow_morning',
        ])
        ->assertOk()
        ->assertJsonPath('scheduled', true);

    expect(ConversationMessage::query()->count())->toBe(0);

    $row = ScheduledOutboundMessage::query()->sole();

    expect($row->type)->toBe(ScheduledOutboundMessageType::SmsReply)
        ->and($row->status)->toBe(ScheduledOutboundMessageStatus::Scheduled)
        ->and($row->customer_id)->toBe($customer->id)
        ->and($row->recipient_phone)->toBe(PhoneNumber::normalize('7195559090'))
        ->and($row->payload_json['body'])->toBe('We will call you tomorrow with an update.')
        ->and($row->scheduled_for->utc()->format('Y-m-d H:i:s'))->toBe('2026-07-22 14:00:00')
        ->and(ShopDisplayTimezone::format($row->scheduled_for, 'Y-m-d H:i:s'))->toBe('2026-07-22 08:00:00');

    Queue::assertPushed(DispatchScheduledOutboundSmsReplyJob::class);
});

test('send now cancels pending scheduled sms reply before sending', function () {
    Queue::fake();
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195559090');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = scheduledOutboundSmsCustomer();

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Scheduled draft',
            'timing' => 'tomorrow_morning',
        ])
        ->assertOk();

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Sending now instead',
            'timing' => 'now',
        ]);

    $response->assertOk()
        ->assertJsonPath('scheduled', false);

    $row = ScheduledOutboundMessage::query()->sole();

    expect($row->status)->toBe(ScheduledOutboundMessageStatus::Cancelled)
        ->and(ConversationMessage::query()->count())->toBe(1);
});

test('scheduled sms job sends through outbound action and snapshots recipient phone', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195559090');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = scheduledOutboundSmsCustomer();

    $row = ScheduledOutboundMessage::query()->create([
        'type' => ScheduledOutboundMessageType::SmsReply,
        'status' => ScheduledOutboundMessageStatus::Scheduled,
        'scheduled_for' => now('UTC')->subMinute(),
        'customer_id' => $customer->id,
        'requested_by_user_id' => $advisor->id,
        'delivery_mode' => 'sms',
        'recipient_phone' => PhoneNumber::normalize('7195559090'),
        'payload_json' => ['body' => 'Morning follow-up'],
        'requested_at' => now('UTC')->subHour(),
    ]);

    $customer->forceFill(['phone' => '7195550000'])->save();

    (new DispatchScheduledOutboundSmsReplyJob($row->id))->handle(
        app(\App\Ark\Operations\Messaging\SendOutboundMessageAction::class),
        app(\App\Ark\Operations\Communications\CancelScheduledOutboundMessagesAction::class),
        app(\App\Ark\Operations\Conversations\ConversationResolver::class),
    );

    $row->refresh();

    expect($row->status)->toBe(ScheduledOutboundMessageStatus::Sent)
        ->and(ConversationMessage::query()->count())->toBe(1);
});

test('scheduled sms job supersedes when shop already replied after requested_at', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195559090');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = scheduledOutboundSmsCustomer();

    $row = ScheduledOutboundMessage::query()->create([
        'type' => ScheduledOutboundMessageType::SmsReply,
        'status' => ScheduledOutboundMessageStatus::Scheduled,
        'scheduled_for' => now('UTC')->subMinute(),
        'customer_id' => $customer->id,
        'requested_by_user_id' => $advisor->id,
        'delivery_mode' => 'sms',
        'recipient_phone' => PhoneNumber::normalize('7195559090'),
        'payload_json' => ['body' => 'Should not send'],
        'requested_at' => now('UTC')->subHour(),
    ]);

    app(\App\Ark\Operations\Messaging\SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'Manual reply already went out',
    );

    (new DispatchScheduledOutboundSmsReplyJob($row->id))->handle(
        app(\App\Ark\Operations\Messaging\SendOutboundMessageAction::class),
        app(\App\Ark\Operations\Communications\CancelScheduledOutboundMessagesAction::class),
        app(\App\Ark\Operations\Conversations\ConversationResolver::class),
    );

    expect($row->fresh()->status)->toBe(ScheduledOutboundMessageStatus::Cancelled)
        ->and(ConversationMessage::query()->count())->toBe(1);
});

test('cancel scheduled sms endpoint clears pending intent', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = scheduledOutboundSmsCustomer();

    ScheduledOutboundMessage::query()->create([
        'type' => ScheduledOutboundMessageType::SmsReply,
        'status' => ScheduledOutboundMessageStatus::Scheduled,
        'scheduled_for' => now('UTC')->addHours(8),
        'customer_id' => $customer->id,
        'requested_by_user_id' => $advisor->id,
        'delivery_mode' => 'sms',
        'recipient_phone' => PhoneNumber::normalize('7195559090'),
        'payload_json' => ['body' => 'Cancel me'],
        'requested_at' => now('UTC'),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-actions.cancel-scheduled-sms', $customer))
        ->assertOk()
        ->assertJsonPath('cancelled', true)
        ->assertJsonPath('sms_schedule.pending', null);

    expect(ScheduledOutboundMessage::query()->sole()->status)
        ->toBe(ScheduledOutboundMessageStatus::Cancelled);
});

test('scheduled sms rejects attachments', function () {
    Queue::fake();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = scheduledOutboundSmsCustomer();

    $this->actingAs($advisor)
        ->post(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'With photo',
            'timing' => 'tomorrow_morning',
            'attachment' => Illuminate\Http\UploadedFile::fake()->image('part.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Remove the attachment to schedule this reply.');

    expect(ScheduledOutboundMessage::query()->count())->toBe(0);
});

test('saturday night schedules monday morning when weekend is closed', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-08-15 21:00:00', 'America/Denver')->utc());

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = scheduledOutboundSmsCustomer();

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'See you Monday.',
            'timing' => 'tomorrow_morning',
        ])
        ->assertOk()
        ->assertJsonPath('scheduled', true);

    $row = ScheduledOutboundMessage::query()->sole();

    expect(ShopDisplayTimezone::format($row->scheduled_for, 'D Y-m-d H:i:s'))
        ->toBe('Mon 2026-08-17 08:00:00');
});

test('advisor can schedule sms reply for a chosen open morning', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-08-15 21:00:00', 'America/Denver')->utc());

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = scheduledOutboundSmsCustomer();
    $tuesdayMorning = Carbon::parse('2026-08-18 08:00:00', 'America/Denver')->utc()->toIso8601String();

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Tuesday works better.',
            'timing' => 'tomorrow_morning',
            'scheduled_for' => $tuesdayMorning,
        ])
        ->assertOk()
        ->assertJsonPath('scheduled', true);

    $row = ScheduledOutboundMessage::query()->sole();

    expect(ShopDisplayTimezone::format($row->scheduled_for, 'D Y-m-d H:i:s'))
        ->toBe('Tue 2026-08-18 08:00:00');
});

function scheduledOutboundSmsCustomer(): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Sms',
        'last_name' => 'Schedule',
        'phone' => '7195559090',
        'email' => 'sms-schedule@example.com',
        'customer_type' => 'Retail',
    ]);

    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => PhoneNumber::normalize('7195559090')],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
        ],
    );

    return $customer;
}
