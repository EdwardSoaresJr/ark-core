<?php

/**
 * H0.2 — Sarah saga.
 *
 * If any of The Six Ones fail after a step, H0 fails. Do not begin H1.
 *
 * @see docs/communications/ark-conversations-v1.md
 */

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\Portal\RecordPortalEstimateViewAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\ConversationsH0;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
            ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'portal_signature_required' => false,
    ]);
    ShopSettings::forgetCurrent();
});

test('H0 Sarah saga — Six Ones after every operational step', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-13 09:00:00', 'UTC'));

    $advisor = User::factory()->create(['name' => 'Molly'])->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195554821',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
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
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake Estimate',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 42000,
    ]);

    $recorder = app(ConversationRecorder::class);
    $events = app(CommunicationEventRecorder::class);
    $phone = PhoneNumber::normalize((string) $customer->phone);
    expect($phone)->not->toBeNull();

    $tick = static function (string $utc): void {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
    };

    // 1. Sarah texts
    $tick('2026-07-13 15:00:00');
    $recorder->recordInboundSms($phone, 'Need brakes.', 'SM-sarah-1', $customer);
    ConversationsH0::assertSixOnes($customer, $repairOrder, $advisor, 'Sarah texts');

    // 2. Advisor replies
    $tick('2026-07-13 15:05:00');
    $recorder->recordOutboundSms($customer, $advisor, 'Can you bring it tomorrow?', 'SM-sarah-2', $repairOrder);
    ConversationsH0::assertSixOnes($customer, $repairOrder, $advisor, 'Advisor replies');

    // 3–5. Missed call + voicemail
    $tick('2026-07-13 16:00:00');
    $call = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-sarah-missed',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195554821',
        'to_number' => '+17195559999',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);
    ConversationsH0::assertSixOnes($customer, $repairOrder, $advisor, 'Missed call');

    $tick('2026-07-13 16:02:00');
    $call->forceFill([
        'voicemail_url' => 'https://api.twilio.com/vm-sarah.wav',
        'voicemail_sid' => 'RE-sarah-vm',
    ])->save();
    ConversationsH0::assertSixOnes($customer, $repairOrder, $advisor, 'Voicemail');

    // 6. Advisor returns call (handles missed) + follow-up text
    $tick('2026-07-13 16:15:00');
    $call->forceFill([
        'worked_at' => now(),
        'status' => CallSessionStatus::Completed,
    ])->save();
    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-sarah-return',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Outbound,
        'from_number' => '+17195559999',
        'to_number' => '+17195554821',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Completed,
        'started_at' => now(),
        'worked_at' => now(),
        'owned_by_user_id' => $advisor->id,
    ]);
    $recorder->recordOutboundSms($customer, $advisor, 'Sending your estimate now.', 'SM-sarah-3', $repairOrder);
    ConversationsH0::assertSixOnes($customer, $repairOrder, $advisor, 'Advisor returned call');

    // 7. Estimate sent
    $tick('2026-07-13 16:20:00');
    $repairOrder->forceFill(['status' => RepairOrderStatus::WaitingApproval])->save();
    $estMsg = $recorder->recordOutboundSms($customer, $advisor, 'Your estimate is ready.', 'SM-sarah-est', $repairOrder);
    $events->record(
        $repairOrder,
        OperationalCommunicationType::EstimateSent,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        'Estimate sent',
        $advisor,
        $estMsg,
        now(),
    );
    ConversationsH0::assertSixOnes($customer, $repairOrder->fresh(), $advisor, 'Estimate sent');

    // 8. Estimate viewed
    $tick('2026-07-13 17:00:00');
    $token = EstimateAccessToken::createForPlainToken($repairOrder, str_repeat('s', 64));
    app(RecordPortalEstimateViewAction::class)->execute($repairOrder->fresh(), $token);
    ConversationsH0::assertSixOnes($customer, $repairOrder->fresh(), $advisor, 'Estimate viewed');

    // 9. Estimate approved
    $tick('2026-07-13 17:30:00');
    $concern->forceFill(['disposition' => RepairOrderConcernDisposition::Approved])->save();
    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 42000,
        'source' => ApprovalSource::Portal,
        'approved_by' => 'Sarah Johnson',
        'approved_at' => now(),
    ]);
    $events->record(
        $repairOrder->fresh(),
        OperationalCommunicationType::ApprovalFollowUp,
        OperationalCommunicationChannel::Website,
        OperationalCommunicationDirection::Inbound,
        'Estimate approved',
        null,
        null,
        now(),
    );
    ConversationsH0::assertSixOnes($customer, $repairOrder->fresh(), $advisor, 'Estimate approved');

    // 10. Inspection complete
    $tick('2026-07-13 20:00:00');
    $repairOrder->forceFill(['status' => RepairOrderStatus::InProgress])->save();
    ConversationsH0::assertSixOnes($customer, $repairOrder->fresh(), $advisor, 'Inspection complete', requireActiveTurn: false);

    // 11. Payment request
    $tick('2026-07-13 21:00:00');
    $payMsg = $recorder->recordOutboundSms($customer, $advisor, 'Payment link for your balance.', 'SM-sarah-pay', $repairOrder);
    $events->record(
        $repairOrder->fresh(),
        OperationalCommunicationType::InvoiceSent,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        'Payment requested',
        $advisor,
        $payMsg,
        now(),
    );
    ConversationsH0::assertSixOnes($customer, $repairOrder->fresh(), $advisor, 'Payment request');

    // 12. Customer paid (story via pickup path — payment is operational; keep Thread active via outbound wait)
    $tick('2026-07-13 21:30:00');
    ConversationsH0::assertSixOnes($customer, $repairOrder->fresh(), $advisor, 'Customer paid', requireActiveTurn: false);

    // 13. Pickup scheduled
    $tick('2026-07-13 22:00:00');
    $pickupMsg = $recorder->recordOutboundSms($customer, $advisor, 'You are scheduled for pickup at 5 PM.', 'SM-sarah-pickup', $repairOrder);
    $events->record(
        $repairOrder->fresh(),
        OperationalCommunicationType::PickupNotified,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        'Pickup scheduled',
        $advisor,
        $pickupMsg,
        now(),
    );
    ConversationsH0::assertSixOnes($customer, $repairOrder->fresh(), $advisor, 'Pickup scheduled');

    // 14. Vehicle picked up
    $tick('2026-07-13 23:00:00');
    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->save();
    ConversationsH0::assertSixOnes($customer, $repairOrder->fresh(), $advisor, 'Vehicle picked up', requireActiveTurn: false);

    Carbon::setTestNow();
});
