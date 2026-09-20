<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationReadTracker;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
        Storage::fake('local');
});

test('communications attention projection skips recent activity feed', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $resolver = app(\App\Ark\Operations\Communications\CommunicationsQueueResolver::class);

    expect($resolver->resolveAttention($advisor)['recent_activity'])->toBe([]);
});

test('advisor comms pressure resolves once per request', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    ingestInboundSms('7195554400', 'Cache pressure count', 'SMpressurecache1');

    $pressure = app(\App\Ark\Operations\Communications\AdvisorCommsPressure::class);
    $first = $pressure->resolve($advisor);
    $second = $pressure->resolve($advisor);

    expect($first)->toBe($second)
        ->and($first['count'])->toBeGreaterThan(0);
});

test('communications queue route redirects to dedicated communications surface', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.communications.queue'))
        ->assertRedirect(CommunicationsNeedsYou::url());

    $this->actingAs($advisor)
        ->get(route('operations.communications.attention-queue'))
        ->assertRedirect(CommunicationsNeedsYou::url());
});

test('work home shows communications metric without queue sections', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Work')
        ->assertDontSee('Planned Work')
        ->assertSee('Communications')
        ->assertSee('Open queue')
        ->assertSee('Clear', false)
        ->assertDontSee('Shop Pressure')
        ->assertDontSee('What needs action right now')
        ->assertDontSee('Communications Queue')
        ->assertDontSee('Since Last Shift')
        ->assertDontSee('Needs Attention')
        ->assertDontSee('Recent Activity')
        ->assertDontSee('id="ops-work-recent-activity"', false)
        ->assertDontSee('Unknown / Unmatched');

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('ops-comms-workspace', false)
        ->assertSee('Needs attention', false)
        ->assertDontSee('id="ops-work-recent-activity"', false);
});

test('communications page filters queue rows by channel tab', function () {
})->skip('Workspace Surface Phase 2: channel tabs on Attention workspace.');

test('recent activity fragment returns html for work polling', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAfragment001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195553333',
        'to_number' => '+17195559999',
        'normalized_from' => '7195553333',
        'status' => CallSessionStatus::Answered,
        'started_at' => now()->subMinute(),
        'handled_at' => now(),
        'handled_notes' => 'Call handled on the floor',
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.communications.recent-activity.fragment'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonStructure(['count', 'recent_activity', 'html']);

    expect($this->actingAs($advisor)->getJson(route('operations.communications.recent-activity.fragment'))->json('html'))
        ->toContain('Unknown Caller')
        ->toContain('Mark Handled');
});

test('communications queue surfaces since last shift items for morning triage', function () {
    Carbon::setTestNow('2026-06-03 08:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill(['last_seen_at' => '2026-06-03 07:00:00'])->save();

    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '7195551001',
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmorning001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551001',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $customer->id,
        'started_at' => '2026-06-03 07:30:00',
    ]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('John Smith');

    Carbon::setTestNow();
});

test('communications queue api includes unhandled missed calls', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '7195551001',
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcomms001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551001',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $customer->id,
        'started_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('needs_attention.0.kind', 'call')
        ->assertJsonPath('needs_attention.0.direction', 'inbound')
        ->assertJsonPath('needs_attention.0.direction_label', 'Incoming')
        ->assertJsonPath('needs_attention.0.headline', 'John Smith')
        ->assertJsonPath('needs_attention.0.status', CallSessionStatus::Missed->value);
});

test('communications queue api includes ringing and active calls', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcomms002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now()->subMinute(),
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcomms003',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195555678',
        'to_number' => '+17195559999',
        'normalized_from' => '7195555678',
        'status' => CallSessionStatus::Answered,
        'started_at' => now()->subMinutes(2),
    ]);

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk();

    $kinds = collect($response->json('needs_attention'))->pluck('status')->all();

    expect($response->json('count'))->toBe(2)
        ->and($kinds)->toContain(CallSessionStatus::Ringing->value, CallSessionStatus::Answered->value);
});

test('communications queue reply url deep links to estimate review when customer has one open ro', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = commsQueueCustomer('Reply', 'DeepLink', '7195554321');
    $vehicle = commsQueueVehicle($customer, 'Toyota', 'Camry');
    $repairOrder = commsQueueRepairOrder($customer, $vehicle, RepairOrderStatus::WaitingApproval, 7701);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil change',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    ingestInboundSms('7195554321', 'Is my car ready?', 'SMreply001');

        
    $expectedReplyUrl = route('operations.repair-orders.show', $repairOrder->repair_order_id)
        .'?compose=text#comms';

    $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk()
        ->assertJsonPath('messages.0.show_reply_action', true)
        ->assertJsonPath('messages.0.reply_url', $expectedReplyUrl);
});

test('communications queue api includes unread inbound sms', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'phone' => '7195551234',
    ]);

    ingestInboundSms('7195551234', 'Can I pick it up Friday?', 'SMqueue001');

    $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('messages.0.kind', 'sms')
        ->assertJsonPath('messages.0.direction', 'inbound')
        ->assertJsonPath('messages.0.direction_label', 'Incoming')
        ->assertJsonPath('messages.0.state', 'unread')
        ->assertJsonPath('messages.0.headline', 'Jane Driver')
        ->assertJsonPath('messages.0.snippet', 'Can I pick it up Friday?');
});

test('communications queue api includes inbound mms with attachment indicator', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    commsQueueCustomer('Photo', 'Sender', '3035550100');

    commsQueueIngestInboundMms('3035550100', 'SMqueue002');

    $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk()
        ->assertJsonPath('messages.0.kind', 'mms')
        ->assertJsonPath('messages.0.has_attachment', true)
        ->assertJsonPath('messages.0.attachment_count', 1);
});

test('unknown caller and texter appear in unknown section', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAunknown001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559999',
        'to_number' => '+17195550100',
        'normalized_from' => '7195559999',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinute(),
    ]);

    ingestInboundSms('7195558888', 'Who is this?', 'SMunknown001');

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk();

    $unknownHeadlines = collect($response->json('unknown'))->pluck('headline')->all();

    expect($unknownHeadlines)->toContain('Unknown');
});

test('unknown unmatched sms shows reply action and can send from conversation reply page', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    ingestInboundSms('3039056841', 'Are you open on the weekends?', 'SMunknownreply001');

    $conversation = Conversation::query()->sole();

    bindFakeOutboundSms('SMunknownreply01');
    seedMobileSmsCapability('3039056841');

        
    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.conversations.reply', $conversation).'?compose=text')
        ->assertRedirect(CommunicationsNeedsYou::url([
            'conversation' => $conversation->id,
            'compose' => 'text',
        ]).'#conversation-composer');

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url([
            'conversation' => $conversation->id,
            'compose' => 'text',
        ]))
        ->assertOk()
        ->assertSee('Are you open on the weekends?')
        ->assertSee('data-ark-conversation-composer', false);

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.messages.store', $conversation), [
            'body' => 'Yes — Saturday 8–12.',
        ])
        ->assertOk()
        ->assertJsonPath('message.body', 'Yes — Saturday 8–12.');
});

test('unknown unmatched sms start intake prefills phone and concern', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    ingestInboundSms('7195557777', 'My Jeep overheats', 'SMintake001');

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk();

    $smsRow = collect($response->json('unknown'))
        ->first(fn (array $row): bool => ($row['kind'] ?? '') === 'sms');

    expect($smsRow)->not->toBeNull()
        ->and($smsRow['snippet'])->toBe('My Jeep overheats')
        ->and($smsRow['intake_url'])->toBe(route('operations.intake.create', [
            'phone' => '7195557777',
            'concern' => 'My Jeep overheats',
        ]));
});

test('handled call disappears from needs attention', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAhandled001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195557777',
        'to_number' => '+17195559999',
        'normalized_from' => '7195557777',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinute(),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.telephony.call-queue.worked', $session))
        ->assertOk();

    $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('needs_attention', []);
});

test('read message without reply stays in needs attention via shop turn', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    commsQueueCustomer('Read', 'Test', '7195552468');

    ingestInboundSms('7195552468', 'Thanks!', 'SMread001');

    $conversation = Conversation::query()->sole();

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.read', $conversation))
        ->assertOk();

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk();

    expect($response->json('count'))->toBeGreaterThan(0)
        ->and(collect($response->json('needs_attention'))->pluck('conversation_id'))->toContain($conversation->id);

    $recentSnippets = collect($response->json('recent_activity'))->pluck('snippet')->all();

    expect($recentSnippets)->toContain('Thanks!');
});

test('aged unreplied mms stays in needs attention via shop turn', function () {
    Carbon::setTestNow('2026-06-30 16:42:40');

    $advisor = actingAsLearnCurrentAdvisor();
    commsQueueCustomer('Aged', 'Mms', '7196416535');

    commsQueueIngestInboundMms('7196416535', 'SMagedmms001');

    $conversation = Conversation::query()->sole();

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.read', $conversation))
        ->assertOk();

    Carbon::setTestNow('2026-07-11 17:00:00');

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.communications.queue.api'))
        ->assertOk();

    $row = collect($response->json('needs_attention'))
        ->first(fn (array $item): bool => (int) ($item['conversation_id'] ?? 0) === $conversation->id);

    expect($row)->not->toBeNull()
        ->and($row['kind'] ?? null)->toBe('mms')
        ->and($row['has_attachment'] ?? false)->toBeTrue();

    Carbon::setTestNow();
});

test('topbar queue count includes calls and unread inbound messages', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcount001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551111',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551111',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinute(),
    ]);

    commsQueueCustomer('Count', 'Test', '7195552222');

    ingestInboundSms('7195552222', 'Any update?', 'SMcount001');

    $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonCount(2, 'items');
});

test('sidebar does not show retired intake queue nav', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Intake',
        'last_name' => 'Pressure',
        'phone' => '7195558801',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Fit',
    ]);
    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Draft scope in build',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('aria-label="1 in intake queue"', false)
        ->assertDontSee('>Intake</span>', false)
        ->assertSee('+ Check In', false)
        ->assertSee(route('operations.intake.create'), false);
});

test('communications sidebar shows pressure count instead of topbar attention button', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    ingestInboundSms('7195554399', 'Need a callback', 'SMsidebar001');

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('ops-rail-link__count', false)
        ->assertSee('need attention"', false)
        ->assertSee('Communications', false)
        ->assertDontSee('ops-call-queue__label">Attention', false)
        ->assertDontSee('ops-call-queue__trigger', false);
});

test('work communications queue lane shows reply for inbound sms and mms', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = commsQueueCustomer('Lane', 'Reply', '7195554321');

    ingestInboundSms('7195554321', 'Is my car ready?', 'SMlane001');
    commsQueueIngestInboundMms('7195554321', 'SMlane002', 'mms/lane002.jpg');

        
    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $expectedReplyUrl = route('operations.customers.show', $customer).'?compose=text#customer-communication';

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('Is my car ready?')
        ->assertSee('Lane Reply', false)
        ->assertSee('>Reply</', false);

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'comms'))
        ->assertRedirect(CommunicationsNeedsYou::url());
});

test('mark conversation read uses existing read tracker schema', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = commsQueueCustomer('Tracker', 'Test', '7195553333');

    ingestInboundSms('7195553333', 'Ping', 'SMtracker001');

    $conversation = Conversation::query()->sole();
    $tracker = app(ConversationReadTracker::class);

    expect($tracker->unreadInboundCount($conversation, $advisor))->toBe(1);

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.read', $conversation))
        ->assertOk();

    expect($tracker->unreadInboundCount($conversation->fresh(), $advisor))->toBe(0);
});

function commsQueueIngestInboundMms(
    string $phone,
    string $messageSid,
    string $storagePath = 'mms/test.jpg',
): array {
    Storage::disk('local')->put($storagePath, 'image-bytes');

    $result = ingestInboundSms($phone, '', $messageSid);
    $message = $result['message'];

    ConversationMessageAttachment::query()->create([
        'conversation_message_id' => $message->id,
        'content_type' => 'image/jpeg',
        'storage_path' => $storagePath,
        'byte_size' => 11,
    ]);

    return $result;
}

function commsQueueCustomer(string $first, string $last, string $phone): Customer
{
    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'customer_type' => 'Retail',
    ]);
}

function commsQueueVehicle(Customer $customer, string $make, string $model): Vehicle
{
    return Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => $make,
        'model' => $model,
    ]);
}

function commsQueueRepairOrder(
    Customer $customer,
    Vehicle $vehicle,
    RepairOrderStatus $status,
    int $repairOrderNumber,
): RepairOrder {
    return RepairOrder::query()->create([
        'repair_order_id' => $repairOrderNumber,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Comms queue test',
    ]);
}
