<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Workboard\WorkboardCardActivityProjection;
use App\Ark\Operations\Workboard\WorkboardCardActivityState;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('activity strip reserves six none marks when nothing happened', function () {
    $repairOrder = new RepairOrder;
    $repairOrder->forceFill(['id' => 21, 'repair_order_id' => 9001, 'customer_id' => 8]);
    $repairOrder->setRelation('communicationEvents', collect());
    $repairOrder->setRelation('lines', collect());

    $marks = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[21];

    expect($marks)->toHaveCount(6)
        ->and(collect($marks)->pluck('key')->all())->toBe(['estimate', 'sms', 'email', 'phone', 'dvi', 'scheduled'])
        ->and(collect($marks)->pluck('state')->all())->toBe([
            WorkboardCardActivityState::None,
            WorkboardCardActivityState::None,
            WorkboardCardActivityState::None,
            WorkboardCardActivityState::None,
            WorkboardCardActivityState::None,
            WorkboardCardActivityState::None,
        ])
        ->and($marks[0]->tooltip)->toBe('Estimate - None')
        ->and($marks[3]->tooltip)->toBe('Phone - None')
        ->and($marks[5]->tooltip)->toBe('Scheduled - None');
});

test('activity strip marks estimate ready from priced lines then viewed over sent', function () {
    Carbon::setTestNow('2026-09-11 12:00:00');

    $line = new RepairOrderLine;
    $line->forceFill(['total_cents' => 14000, 'unit_price_cents' => 14000]);

    $repairOrder = new RepairOrder;
    $repairOrder->forceFill(['id' => 22, 'repair_order_id' => 9002]);
    $repairOrder->setRelation('communicationEvents', collect());
    $repairOrder->setRelation('lines', collect([$line]));

    $ready = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[22][0];

    expect($ready->state)->toBe(WorkboardCardActivityState::Ready)
        ->and($ready->tooltip)->toBe('Estimate - Ready');

    $sent = new CommunicationEvent;
    $sent->forceFill([
        'event_type' => OperationalCommunicationType::EstimateSent,
        'channel' => OperationalCommunicationChannel::Sms,
        'occurred_at' => now()->subDays(3),
    ]);

    $viewed = new CommunicationEvent;
    $viewed->forceFill([
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'occurred_at' => now()->subDays(14),
    ]);

    $repairOrder->setRelation('communicationEvents', collect([$sent, $viewed]));

    $engaged = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[22][0];

    expect($engaged->state)->toBe(WorkboardCardActivityState::Engaged)
        ->and($engaged->badge)->toBe('viewed')
        ->and($engaged->tooltip)->toBe('Estimate - Viewed 2w ago');

    Carbon::setTestNow();
});

test('activity strip lights phone attention for an unworked miss on this repair order', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::Estimate);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAactivity001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551001',
        'status' => CallSessionStatus::Missed,
        'repair_order_id' => $repairOrder->id,
        'started_at' => now()->subDays(3),
    ]);

    $marks = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[$repairOrder->id];

    expect($marks[3]->state)->toBe(WorkboardCardActivityState::Attention)
        ->and($marks[3]->badge)->toBe('alert')
        ->and($marks[3]->tooltip)->toContain('Missed call');
});

test('activity strip treats a worked missed call as call activity not attention', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::Estimate);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAactivity002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551002',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551002',
        'status' => CallSessionStatus::Missed,
        'repair_order_id' => $repairOrder->id,
        'worked_at' => now()->subDay(),
        'started_at' => now()->subDays(3),
    ]);

    $marks = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[$repairOrder->id];

    expect($marks[3]->state)->toBe(WorkboardCardActivityState::Sent)
        ->and($marks[3]->tooltip)->toContain('Inbound call');
});

test('activity strip marks dvi on file from the inspection on this repair order', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::Approved);

    Inspection::query()->create([
        'repair_order_id' => $repairOrder->id,
        'started_at' => now()->subHours(5),
    ]);

    $marks = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[$repairOrder->id];

    expect($marks[4]->state)->toBe(WorkboardCardActivityState::Ready)
        ->and($marks[4]->tooltip)->toStartWith('DVI - On file');
});

test('activity strip lights sms attention when delivery failed', function () {
    Carbon::setTestNow('2026-09-11 12:00:00');

    $failed = new CommunicationEvent;
    $failed->forceFill([
        'event_type' => OperationalCommunicationType::SmsDeliveryFailed,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'occurred_at' => now()->subHours(2),
    ]);

    $repairOrder = new RepairOrder;
    $repairOrder->forceFill(['id' => 24, 'repair_order_id' => 9004]);
    $repairOrder->setRelation('communicationEvents', collect([$failed]));

    $marks = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[24];

    expect($marks[1]->state)->toBe(WorkboardCardActivityState::Attention)
        ->and($marks[1]->badge)->toBe('alert')
        ->and($marks[1]->tooltip)->toBe('SMS - Delivery failed 2h ago');

    Carbon::setTestNow();
});

test('activity strip marks sms engaged when the customer replied on this repair order', function () {
    Carbon::setTestNow('2026-09-11 12:00:00');

    $reply = new CommunicationEvent;
    $reply->forceFill([
        'event_type' => OperationalCommunicationType::CustomerReply,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'occurred_at' => now()->subDay(),
    ]);

    $repairOrder = new RepairOrder;
    $repairOrder->forceFill(['id' => 23, 'repair_order_id' => 9003]);
    $repairOrder->setRelation('communicationEvents', collect([$reply]));

    $marks = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[23];

    expect($marks[1]->state)->toBe(WorkboardCardActivityState::Engaged)
        ->and($marks[1]->badge)->toBe('replied')
        ->and($marks[1]->tooltip)->toBe('SMS - Customer replied 1d ago');

    Carbon::setTestNow();
});

test('activity strip lights scheduled today and missed appointments', function () {
    Carbon::setTestNow(ShopDisplayTimezone::parseLocal('2026-08-24 09:00')->utc());

    $repairOrder = repairOrderForCommunication(RepairOrderStatus::Estimate);
    $advisor = actingAsLearnCurrentAdvisor();

    Appointment::query()->create([
        'customer_id' => $repairOrder->customer_id,
        'vehicle_id' => $repairOrder->vehicle_id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-08-24 14:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-08-24 15:00')->utc(),
        'concern' => 'Brake noise',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $today = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[$repairOrder->id][5];

    expect($today->state)->toBe(WorkboardCardActivityState::Sent)
        ->and($today->tooltip)->toBe('Scheduled - Today 2:00 PM');

    Appointment::query()->where('repair_order_id', $repairOrder->id)->update([
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-08-24 08:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-08-24 09:00')->utc(),
    ]);

    $missed = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder->fresh()]))[$repairOrder->id][5];

    expect($missed->state)->toBe(WorkboardCardActivityState::Attention)
        ->and($missed->badge)->toBe('alert')
        ->and($missed->tooltip)->toStartWith('Scheduled - Missed');

    Carbon::setTestNow();
});

test('activity strip marks checked-in appointments as engaged', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress);
    $advisor = actingAsLearnCurrentAdvisor();

    Appointment::query()->create([
        'customer_id' => $repairOrder->customer_id,
        'vehicle_id' => $repairOrder->vehicle_id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'concern' => 'Brake noise',
        'status' => AppointmentStatus::Arrived,
    ]);

    $marks = (new WorkboardCardActivityProjection)->map(new Collection([$repairOrder]))[$repairOrder->id];

    expect($marks[5]->state)->toBe(WorkboardCardActivityState::Engaged)
        ->and($marks[5]->tooltip)->toBe('Scheduled - Checked in');
});
