<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Communications\CommunicationsQueueResolver;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Recommendations\RecommendationResolution;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Staff\StaffFrontDoor;
use App\Ark\Operations\Today\Lifecycle\EstimateFollowUpLifecycle;
use App\Ark\Operations\Today\Lifecycle\TodayCompletionEvent;
use App\Ark\Operations\Today\Lifecycle\TodayRecommendationKind;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    fakeCommsWorkspaceTwilio();
            config()->set('broadcasting.default', 'null');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'portal_signature_required' => false,
    ]);
});

function fakeCommsWorkspaceTwilio(string $messageSid = 'SMworkspace001'): void
{
    Http::fake([
        'lookups.twilio.com/*' => Http::response([
            'calling_country_code' => '1',
            'country_code' => 'US',
            'phone_number' => '+17195551234',
            'national_format' => '(719) 555-1234',
            'valid' => true,
            'validation_errors' => null,
            'line_type_intelligence' => [
                'error_code' => null,
                'mobile_country_code' => '310',
                'mobile_network_code' => '260',
                'carrier_name' => 'T-Mobile USA',
                'type' => 'mobile',
            ],
        ], 200),
        'https://api.twilio.com/*' => Http::response([
            'sid' => $messageSid,
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();
}

function commsWorkspaceAdvisor(): User
{
    return actingAsLearnCurrentAdvisor();
}

test('website lead appears in needs attention with turn label', function (): void {
    $advisor = commsWorkspaceAdvisor();

    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes squeal when stopping.',
        'phone' => '719-555-0142',
        'first_name' => 'Jason',
        'last_name' => 'Smith',
        'source' => LeadSource::Website->value,
    ])->assertRedirect();

    $lead = Lead::query()->firstOrFail();
    $conversation = Conversation::query()->findOrFail($lead->conversation_id);

    expect($conversation->waiting_on)->toBe(ConversationWaitingOn::Shop);

    $queue = app(CommunicationsQueueResolver::class)->resolveAttention($advisor);
    $rows = collect($queue['needs_attention']);
    $row = $rows->first(fn (array $item): bool => (int) ($item['conversation_id'] ?? 0) === $conversation->id);

    expect($queue['count'])->toBeGreaterThan(0)
        ->and($rows->pluck('headline'))->toContain('Jason Smith')
        ->and($row['state_label'] ?? null)->toBe('Needs first response')
        ->and($row['channel_label'] ?? null)->toBe('Website Lead');
});

test('new ro from unmatched website lead carries lead_id so intake prefills name', function (): void {
    $advisor = commsWorkspaceAdvisor();

    $this->post(route('public.leads.store'), [
        'concern' => 'Need front and rear brakes.',
        'phone' => '719-555-0166',
        'email' => 'kyle@example.test',
        'first_name' => 'Kyle',
        'last_name' => 'Kight',
        'source' => LeadSource::Website->value,
    ])->assertRedirect();

    $lead = Lead::query()->firstOrFail();
    $conversation = Conversation::query()->findOrFail($lead->conversation_id);

    $identity = app(\App\Ark\Operations\Communications\CommunicationsWorkspaceIdentityProjection::class)
        ->forConversation($conversation, lead: $lead);

    $newRo = collect($identity['actions'])->firstWhere('key', 'new_ro');

    expect($identity['name'])->toBe('Kyle Kight')
        ->and($identity['email'])->toBe('kyle@example.test')
        ->and($identity['known_customer'])->toBeFalse()
        ->and($newRo['url'] ?? null)->toContain('lead_id='.$lead->id);

    $context = app(\App\Ark\Operations\Communications\CommunicationsWorkspaceContextBuilder::class)
        ->forConversation($conversation);

    expect($context['sections']['customer']['Email'] ?? null)->toBe('kyle@example.test')
        ->and($context['link_status'] ?? null)->toBe('Lead linked');

    $this->actingAs($advisor)
        ->followingRedirects()
        ->get($newRo['url'])
        ->assertOk()
        ->assertSee('value="Kyle"', false)
        ->assertSee('value="Kight"', false)
        ->assertSee('kyle@example.test', false);
});

test('advisor reply moves conversation to waiting on customer and records first contact', function (): void {
    $advisor = commsWorkspaceAdvisor();

    $this->post(route('public.leads.store'), [
        'concern' => 'Check engine light is on.',
        'phone' => '719-555-0199',
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'source' => LeadSource::Website->value,
    ])->assertRedirect();

    $lead = Lead::query()->firstOrFail();
    $conversation = Conversation::query()->findOrFail($lead->conversation_id);

    fakeCommsWorkspaceTwilio('SMleadreply001');
    $this->travel(2)->seconds();

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.messages.store', $conversation), [
            'body' => 'Thanks Maria — we received your request and will follow up shortly.',
        ])
        ->assertOk();

    $conversation->refresh();
    $lead->refresh();

    expect($conversation->waiting_on)->toBe(ConversationWaitingOn::Customer)
        ->and($lead->first_contacted_at)->not->toBeNull();

    $queue = app(CommunicationsQueueResolver::class)->resolveAttention($advisor);

    expect(collect($queue['needs_attention'])->pluck('conversation_id'))->not->toContain($conversation->id);
});

test('customer inbound sms returns conversation to needs attention', function (): void {
    $advisor = commsWorkspaceAdvisor();

    $this->post(route('public.leads.store'), [
        'concern' => 'Need an oil change.',
        'phone' => '719-555-0200',
        'first_name' => 'Sam',
        'last_name' => 'Rivera',
        'source' => LeadSource::Website->value,
    ])->assertRedirect();

    $lead = Lead::query()->firstOrFail();
    $conversation = Conversation::query()->findOrFail($lead->conversation_id);

    fakeCommsWorkspaceTwilio('SMleadreply002');
    $this->travel(2)->seconds();

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.messages.store', $conversation), [
            'body' => 'We can get you on the schedule — what day works?',
        ])
        ->assertOk();

    
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMcustomerreply001',
        'From' => '+17195550200',
        'To' => '+17195559999',
        'Body' => 'Thursday morning works.',
        'NumMedia' => '0',
    ])->assertOk();

    expect(Lead::query()->where('conversation_id', $conversation->id)->count())->toBe(1);

    $queue = app(CommunicationsQueueResolver::class)->resolveAttention($advisor);
    $row = collect($queue['needs_attention'])->first(
        fn (array $item): bool => (int) ($item['conversation_id'] ?? 0) === $conversation->id,
    );

    expect($row)->not->toBeNull()
        ->and($row['state_label'] ?? null)->toBe('Customer replied');
});

test('lead selection redirects to conversation thread', function (): void {
    $advisor = commsWorkspaceAdvisor();

    $this->post(route('public.leads.store'), [
        'concern' => 'AC not cold.',
        'phone' => '719-555-0301',
        'first_name' => 'Taylor',
        'last_name' => 'Reed',
        'source' => LeadSource::Website->value,
    ])->assertRedirect();

    $lead = Lead::query()->firstOrFail();

    $response = $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['lead' => $lead->id]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
    expect((int) ($query['conversation'] ?? 0))->toBe((int) $lead->conversation_id)
        ->and($query['filter'] ?? null)->toBe('needs');
});

test('send estimate from conversation thread stays on same conversation', function (): void {
    $advisor = commsWorkspaceAdvisor();

    $this->post(route('public.leads.store'), [
        'concern' => 'Brake pedal soft.',
        'phone' => '719-555-0302',
        'first_name' => 'Chris',
        'last_name' => 'Allen',
        'source' => LeadSource::Website->value,
    ])->assertRedirect();

    $lead = Lead::query()->firstOrFail();
    $conversation = Conversation::query()->findOrFail($lead->conversation_id);

    $customer = Customer::query()->create([
        'first_name' => 'Chris',
        'last_name' => 'Allen',
        'phone' => '7195550302',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 9302,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake pedal soft.',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $lead->update([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => $repairOrder->id,
    ]);

    fakeCommsWorkspaceTwilio('SMestthread001');

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.communications.conversations.send-estimate', $conversation))
        ->assertOk()
        ->assertJsonStructure(['estimate_url', 'message_id', 'html']);

    $message = ConversationMessage::query()->findOrFail($response->json('message_id'));

    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->body)->toContain('Your estimate is ready:');
});

test('conversation thread send payment records sms on the active conversation', function (): void {
    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');

    ShopSettings::current()->update([
        'square_enabled' => true,
        'square_portal_pay_enabled' => true,
    ]);

    $advisor = commsWorkspaceAdvisor();
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill([
        'phone' => '7195550404',
        'email' => 'not-an-email',
    ])->save();
    issueFinalInvoiceFor($repairOrder);

    $conversation = app(ConversationResolver::class)->forPhone('7195550404');

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes due.',
        'contact_phone' => '7195550404',
        'contact_name' => 'Pay Thread',
        'conversation_id' => $conversation->id,
        'customer_id' => $repairOrder->customer_id,
        'vehicle_id' => $repairOrder->vehicle_id,
        'repair_order_id' => $repairOrder->id,
    ]);

    fakeCommsWorkspaceTwilio('SMpaythread001');

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.communications.conversations.send-payment', $conversation), [
            'delivery' => 'sms',
            'email' => 'not-an-email',
        ])
        ->assertOk()
        ->assertJsonStructure(['payment_url', 'balance_due_display', 'message_id', 'html']);

    $message = ConversationMessage::query()->findOrFail($response->json('message_id'));

    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->body)->toContain('Balance due')
        ->and($message->body)->toContain('Pay here:');
});

test('estimate approval after conversation send retires today estimate follow-up', function (): void {
    $advisor = commsWorkspaceAdvisor();

    $conversation = app(ConversationResolver::class)->forPhone('7195550403');

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brake pedal soft.',
        'contact_phone' => '7195550403',
        'contact_name' => 'Jordan Lee',
        'conversation_id' => $conversation->id,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'phone' => '7195550403',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 9403,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake pedal soft.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    foreach (range(1, 3) as $index) {
        CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Customer opened estimate portal',
            'occurred_at' => now()->subHours(4 - $index),
        ]);
    }

    expect(app(EstimateFollowUpLifecycle::class)->isActive($repairOrder->fresh(['communicationEvents'])))->toBeTrue()
        ->and($conversation->id)->toBeGreaterThan(0);

    $repairOrder->forceFill(['status' => RepairOrderStatus::Approved])->save();

    app(\App\Ark\Operations\Events\OperationalEventRecorder::class)->record(
        \App\Ark\Operations\Events\OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder->fresh(),
        actor: $advisor,
        payload: [
            'from_status' => RepairOrderStatus::WaitingApproval->value,
            'to_status' => RepairOrderStatus::Approved->value,
        ],
    );

    expect(app(EstimateFollowUpLifecycle::class)->isActive($repairOrder->fresh(['communicationEvents'])))->toBeFalse();

    $resolution = RecommendationResolution::query()->sole();

    expect($resolution->recommendation_kind)->toBe(TodayRecommendationKind::EstimateFollowUp->value)
        ->and($resolution->completion_event)->toBe(TodayCompletionEvent::EstimateApproved->value)
        ->and($resolution->aggregate_id)->toBe(9403);
});

test('advisor front door lands on today', function (): void {
    $advisor = commsWorkspaceAdvisor();

    expect(StaffFrontDoor::landingRouteName($advisor))->toBe('operations.today');

    $this->actingAs($advisor)
        ->get(route('dashboard'))
        ->assertRedirect(route('operations.today'));
});

test('technician front door still lands on today', function (): void {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);

    expect(StaffFrontDoor::landingRouteName($technician))->toBe('operations.today');

    $this->actingAs($technician)
        ->get(route('dashboard'))
        ->assertRedirect(route('operations.today'));
});
