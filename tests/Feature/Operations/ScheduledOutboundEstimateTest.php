<?php

use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\DispatchScheduledOutboundEstimateJob;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Communications\ScheduledOutboundMessage;
use App\Ark\Operations\Communications\ScheduledOutboundMessageStatus;
use App\Ark\Operations\Communications\ScheduledOutboundMessageType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
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

test('tomorrow morning schedules outbound intent without sending', function () {
    Queue::fake();
    // 2026-07-21 22:30 America/Denver == 2026-07-22 04:30 UTC
    Carbon::setTestNow(Carbon::parse('2026-07-22 04:30:00', 'UTC'));

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = scheduledOutboundEstimateRepairOrder();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder), [
            'delivery' => 'sms',
            'timing' => 'tomorrow_morning',
        ])
        ->assertOk()
        ->assertJsonPath('scheduled', true);

    expect(ConversationMessage::query()->count())->toBe(0)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateSent)->count())->toBe(0);

    $row = ScheduledOutboundMessage::query()->sole();

    expect($row->type)->toBe(ScheduledOutboundMessageType::EstimateSend)
        ->and($row->status)->toBe(ScheduledOutboundMessageStatus::Scheduled)
        ->and($row->recipient_phone)->toBe(PhoneNumber::normalize('7195558080'))
        ->and($row->delivery_mode->value)->toBe('sms')
        ->and($row->scheduled_for->utc()->format('Y-m-d H:i:s'))->toBe('2026-07-22 14:00:00')
        ->and(ShopDisplayTimezone::format($row->scheduled_for, 'Y-m-d H:i:s'))->toBe('2026-07-22 08:00:00');

    Queue::assertPushed(DispatchScheduledOutboundEstimateJob::class);
});

test('send now cancels pending scheduled estimate before sending', function () {
    Queue::fake();
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = scheduledOutboundEstimateRepairOrder();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder), [
            'delivery' => 'sms',
            'timing' => 'tomorrow_morning',
        ])
        ->assertOk();

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder), [
            'delivery' => 'sms',
            'timing' => 'now',
        ]);

    $response->assertOk()
        ->assertJsonPath('scheduled', false);

    $row = ScheduledOutboundMessage::query()->sole();

    expect($row->status)->toBe(ScheduledOutboundMessageStatus::Cancelled)
        ->and(ConversationMessage::query()->count())->toBe(1)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateSent)->exists())->toBeTrue();
});

test('scheduled job sends through delivery action and snapshots recipient phone', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = scheduledOutboundEstimateRepairOrder();

    $row = ScheduledOutboundMessage::query()->create([
        'type' => ScheduledOutboundMessageType::EstimateSend,
        'status' => ScheduledOutboundMessageStatus::Scheduled,
        'scheduled_for' => now('UTC')->subMinute(),
        'repair_order_id' => $repairOrder->id,
        'requested_by_user_id' => $advisor->id,
        'delivery_mode' => 'sms',
        'recipient_phone' => PhoneNumber::normalize('7195558080'),
        'recipient_email' => null,
        'payload_json' => null,
        'requested_at' => now('UTC')->subHour(),
    ]);

    $repairOrder->customer->forceFill(['phone' => '7195550000'])->save();

    (new DispatchScheduledOutboundEstimateJob($row->id))->handle(
        app(\App\Ark\Operations\Messaging\SendEstimateDeliveryAction::class),
        app(\App\Ark\Operations\Communications\CancelScheduledOutboundMessagesAction::class),
    );

    $row->refresh();

    expect($row->status)->toBe(ScheduledOutboundMessageStatus::Sent)
        ->and(ConversationMessage::query()->count())->toBe(1)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateSent)->exists())->toBeTrue();
});

test('scheduled job supersedes when estimate was sent after requested_at', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = scheduledOutboundEstimateRepairOrder();

    $row = ScheduledOutboundMessage::query()->create([
        'type' => ScheduledOutboundMessageType::EstimateSend,
        'status' => ScheduledOutboundMessageStatus::Scheduled,
        'scheduled_for' => now('UTC')->subMinute(),
        'repair_order_id' => $repairOrder->id,
        'requested_by_user_id' => $advisor->id,
        'delivery_mode' => 'sms',
        'recipient_phone' => PhoneNumber::normalize('7195558080'),
        'requested_at' => now('UTC')->subHour(),
    ]);

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'created_by' => $advisor->id,
        'event_type' => OperationalCommunicationType::EstimateSent,
        'channel' => 'sms',
        'direction' => 'outbound',
        'summary' => 'Estimate sent manually.',
        'occurred_at' => now('UTC')->subMinutes(10),
    ]);

    (new DispatchScheduledOutboundEstimateJob($row->id))->handle(
        app(\App\Ark\Operations\Messaging\SendEstimateDeliveryAction::class),
        app(\App\Ark\Operations\Communications\CancelScheduledOutboundMessagesAction::class),
    );

    expect($row->fresh()->status)->toBe(ScheduledOutboundMessageStatus::Cancelled)
        ->and(ConversationMessage::query()->count())->toBe(0);
});

test('cancel scheduled estimate endpoint clears pending intent', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = scheduledOutboundEstimateRepairOrder();

    ScheduledOutboundMessage::query()->create([
        'type' => ScheduledOutboundMessageType::EstimateSend,
        'status' => ScheduledOutboundMessageStatus::Scheduled,
        'scheduled_for' => now('UTC')->addHours(8),
        'repair_order_id' => $repairOrder->id,
        'requested_by_user_id' => $advisor->id,
        'delivery_mode' => 'sms',
        'recipient_phone' => PhoneNumber::normalize('7195558080'),
        'requested_at' => now('UTC'),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.cancel-scheduled-estimate', $repairOrder))
        ->assertOk()
        ->assertJsonPath('cancelled', true)
        ->assertJsonPath('schedule.pending', null);

    expect(ScheduledOutboundMessage::query()->sole()->status)
        ->toBe(ScheduledOutboundMessageStatus::Cancelled);
});

function scheduledOutboundEstimateRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Schedule',
        'last_name' => 'Customer',
        'phone' => '7195558080',
        'email' => 'schedule@example.com',
        'customer_type' => 'Retail',
    ]);

    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => PhoneNumber::normalize('7195558080')],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
        ],
    );

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'vin' => '1C4HJXDG6EW654321',
        'normalized_vin' => '1C4HJXDG6EW654321',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake noise',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake service',
        'quantity' => '1.5',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 24750,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 24750,
    ]);

    return $repairOrder->fresh(['customer', 'vehicle', 'lines']);
}
