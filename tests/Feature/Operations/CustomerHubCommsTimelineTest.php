<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\CustomerHubCommsTimeline;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

test('customer hub comms timeline merges calls and messages chronologically', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Timeline Customer');
    $customer = $repairOrder->customer;
    $recorder = app(ConversationRecorder::class);

    Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00'));
    $loggedMessage = $recorder->recordAdvisorLog(
        $repairOrder,
        $advisor,
        OperationalCommunicationChannel::Phone,
        OperationalCommunicationDirection::Inbound,
        'Logged phone contact.',
    );

    Carbon::setTestNow(Carbon::parse('2026-06-02 10:00:00'));
    $textMessage = $recorder->recordAdvisorLog(
        $repairOrder,
        $advisor,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Inbound,
        'Text message body.',
    );

    Carbon::setTestNow(Carbon::parse('2026-06-04 11:00:00'));
    $emailMessage = $recorder->recordAdvisorLog(
        $repairOrder,
        $advisor,
        OperationalCommunicationChannel::Email,
        OperationalCommunicationDirection::Outbound,
        'Email body.',
    );

    Carbon::setTestNow();

    $olderCall = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAolder001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551000',
        'status' => CallSessionStatus::Completed,
        'customer_id' => $customer->id,
        'started_at' => Carbon::parse('2026-06-01 09:00:00'),
    ]);

    $newerCall = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnewer001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551000',
        'status' => CallSessionStatus::Completed,
        'customer_id' => $customer->id,
        'started_at' => Carbon::parse('2026-06-03 09:00:00'),
    ]);

    $timeline = app(CustomerHubCommsTimeline::class);
    $messages = ConversationMessage::query()
        ->whereIn('id', [$loggedMessage->id, $textMessage->id, $emailMessage->id])
        ->get();

    $rows = $timeline->build($messages, collect([$olderCall, $newerCall]));

    expect($rows)->toHaveCount(5)
        ->and($rows[0]->hubFilter())->toBe('email')
        ->and($rows[1]->source)->toBe(OperationalEventSource::CallSession)
        ->and($rows[2]->hubFilter())->toBe('text')
        ->and($rows[3]->source)->toBe(OperationalEventSource::CallSession)
        ->and($rows[4]->hubFilter())->toBe('logged');

    expect($timeline->counts($rows))->toBe([
        'all' => 5,
        'call' => 2,
        'text' => 1,
        'email' => 1,
        'portal' => 0,
        'logged' => 1,
    ]);
});

test('customer hub comms tab renders unified timeline filters', function () {
    $this->seed(ArkAuthorizationSeeder::class);
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => false,
        ]),
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Filter Customer');
    $customer = $repairOrder->customer;

    app(ConversationRecorder::class)->recordAdvisorLog(
        $repairOrder,
        auth()->user(),
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Inbound,
        'Need an update please.',
    );

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAfilter001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551000',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551000',
        'status' => CallSessionStatus::Completed,
        'customer_id' => $customer->id,
        'started_at' => now()->subHour(),
    ]);

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('All ·')
        ->assertSee('Calls ·')
        ->assertSee('Text ·')
        ->assertSee('Attach Picture?')
        ->assertSee('Need an update please.')
        ->assertSee('customer-communication', false);
});

test('customer hub comms updates returns messages newer than since id', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Updates Customer');
    $customer = $repairOrder->customer;

    $first = app(ConversationRecorder::class)->recordAdvisorLog(
        $repairOrder,
        auth()->user(),
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Inbound,
        'First ping.',
    );

    $second = app(ConversationRecorder::class)->recordAdvisorLog(
        $repairOrder,
        auth()->user(),
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Inbound,
        'Second ping.',
    );

    $this->getJson(route('operations.customers.hub-comms.updates', [
        'customer' => $customer,
        'since_message_id' => $first->id,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.message_id', $second->id)
        ->assertJsonPath('items.0.filter', 'text')
        ->assertSee('Second ping.', false);
});
