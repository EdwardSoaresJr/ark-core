<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Attention\AdvisorNudgeDraftBuilder;
use App\Ark\Operations\Attention\ConversationAttentionObservationFilter;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Observations\OperationalObservation;
use App\Ark\Operations\Observations\OperationalObservationSeverity;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
        });

test('estimate view observations are suppressed when customer has work in progress', function (): void {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Jean Luc');
    $customer = $repairOrder->customer;
    $customer->update(['phone' => '7195558801']);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558801',
        'status' => ConversationStatus::Open,
        'owned_by_user_id' => User::factory()->create()->id,
    ]);

    $observations = [
        new OperationalObservation(
            type: OperationalObservationType::EstimateViewed,
            severity: OperationalObservationSeverity::Medium,
            occurredAt: now(),
            customerId: $customer->id,
            vehicleId: null,
            repairOrderId: $repairOrder->id,
            conversationId: $conversation->id,
            headline: 'Estimate viewed',
            description: 'Portal view',
            sourceEvents: [],
            metadata: [],
        ),
        new OperationalObservation(
            type: OperationalObservationType::CustomerSentMultipleMessages,
            severity: OperationalObservationSeverity::Medium,
            occurredAt: now(),
            customerId: $customer->id,
            vehicleId: null,
            repairOrderId: null,
            conversationId: $conversation->id,
            headline: 'Multiple messages',
            description: '2 messages',
            sourceEvents: [],
            metadata: ['message_count' => 2],
        ),
    ];

    $filtered = app(ConversationAttentionObservationFilter::class)
        ->filter($observations, $conversation, $customer->id);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]->type)->toBe(OperationalObservationType::CustomerSentMultipleMessages);
});

test('estimate view observations remain when customer is still waiting approval', function (): void {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Pending Approval');
    $customer = $repairOrder->customer;
    $customer->update(['phone' => '7195558802']);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195558802',
        'status' => ConversationStatus::Open,
        'owned_by_user_id' => User::factory()->create()->id,
    ]);

    $observations = [
        new OperationalObservation(
            type: OperationalObservationType::EstimateViewed,
            severity: OperationalObservationSeverity::Medium,
            occurredAt: now(),
            customerId: $customer->id,
            vehicleId: null,
            repairOrderId: $repairOrder->id,
            conversationId: $conversation->id,
            headline: 'Estimate viewed',
            description: 'Portal view',
            sourceEvents: [],
            metadata: [],
        ),
    ];

    $filtered = app(ConversationAttentionObservationFilter::class)
        ->filter($observations, $conversation, $customer->id);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]->type)->toBe(OperationalObservationType::EstimateViewed);
});

test('call analysis suggested reply becomes call note draft text', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Jean',
        'last_name' => 'Luc',
        'phone' => '7195558813',
        'email' => 'jean.luc@example.test',
    ]);

    $draft = app(AdvisorNudgeDraftBuilder::class)->forNudge([
        'key' => 'call.analysis_follow_up',
        'message' => 'Customer wants brake quote emailed before end of day.',
        'suggested_reply' => 'I will email the brake quote before we close today.',
    ], null, $customer);

    expect($draft)->toBe('Jean — I will email the brake quote before we close today.');
});

test('call analysis draft falls back to follow up message when suggested reply missing', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Jean',
        'last_name' => 'Luc',
        'phone' => '7195558804',
        'email' => 'jean.luc@example.test',
    ]);

    $draft = app(AdvisorNudgeDraftBuilder::class)->forNudge([
        'key' => 'call.analysis_follow_up',
        'message' => 'Customer wants brake quote emailed before end of day.',
    ], null, $customer);

    expect($draft)->toBe('Jean — Customer wants brake quote emailed before end of day.');
});

test('sms analysis suggested reply becomes composer draft text', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'phone' => '7195558805',
        'email' => 'maria@example.test',
    ]);

    $draft = app(AdvisorNudgeDraftBuilder::class)->forNudge([
        'key' => 'conversation.sms_analysis_follow_up',
        'message' => 'Customer asked about appointment availability.',
        'suggested_reply' => 'We have openings tomorrow morning if that works for you.',
    ], null, $customer);

    expect($draft)->toBe('Hi Maria, We have openings tomorrow morning if that works for you.');
});

test('sms analysis draft ignores owner coaching notes when suggested reply missing', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Kent',
        'last_name' => 'Stinson',
        'phone' => '7195558812',
        'email' => 'kent@example.test',
    ]);

    $draft = app(AdvisorNudgeDraftBuilder::class)->forNudge([
        'key' => 'conversation.sms_analysis_follow_up',
        'message' => 'The advisor should have replied to confirm the visit or provide any necessary information.',
        'follow_up_notes' => 'The advisor should have replied to confirm the visit or provide any necessary information.',
    ], null, $customer);

    expect($draft)->toBeNull();
});

test('sms analysis draft rejects coaching text masquerading as suggested reply', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Kent',
        'last_name' => 'Stinson',
        'phone' => '7195558814',
        'email' => 'kent2@example.test',
    ]);

    $draft = app(AdvisorNudgeDraftBuilder::class)->forNudge([
        'key' => 'conversation.sms_analysis_follow_up',
        'suggested_reply' => 'The advisor should have replied to confirm the visit.',
    ], null, $customer);

    expect($draft)->toBeNull();
});

test('stale sms analysis does not show follow up nudge after advisor replies', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Kent',
        'last_name' => 'Stinson',
        'phone' => '7195558813',
        'email' => 'kent@example.test',
    ]);

    $recorder = app(ConversationRecorder::class);
    $recorder->recordInboundSms('7195558813', 'Happy Fourth! I will come by Monday.', 'SMstale001', $customer);

    $conversation = Conversation::query()->where('contact_address', '7195558813')->firstOrFail();
    $conversation->update(['owned_by_user_id' => $advisor->id]);

    $slice = ConversationSmsIntelligenceSlice::query()->where('conversation_id', $conversation->id)->firstOrFail();
    $slice->forceFill([
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Customer plans to visit Monday.',
            'follow_up_needed' => true,
            'follow_up_notes' => 'The advisor should have replied to confirm the visit or provide any necessary information.',
        ],
        'analyzed_at' => now()->subHour(),
        'last_message_at' => now()->subMinutes(5),
    ])->saveQuietly();

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertOk()
        ->assertDontSee('SMS follow-up suggested', false)
        ->assertDontSee('The advisor should have replied', false);
});

test('assigned conversation with sms analysis shows follow up nudge and draft', function (): void {
    Queue::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'phone' => '7195558810',
        'email' => 'maria@example.test',
    ]);

    $recorder = app(ConversationRecorder::class);
    $recorder->recordInboundSms('7195558810', 'Can I get an appointment tomorrow?', 'SManalysis001', $customer);

    $conversation = Conversation::query()->where('contact_address', '7195558810')->firstOrFail();
    $conversation->update(['owned_by_user_id' => $advisor->id]);

    $slice = ConversationSmsIntelligenceSlice::query()->where('conversation_id', $conversation->id)->firstOrFail();
    $slice->forceFill([
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Customer asked about appointment availability.',
            'follow_up_needed' => true,
            'follow_up_notes' => 'Confirm tomorrow morning slot.',
            'suggested_reply' => 'We have openings tomorrow morning if that works for you.',
        ],
        'last_message_at' => now()->subMinute(),
        'analyzed_at' => now(),
    ])->saveQuietly();

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('SMS follow-up suggested', false)
        ->assertSee('We have openings tomorrow morning if that works for you.', false)
        ->assertSee('Reply', false);
});

test('analysis insight panel shows when mark handled blocks call analysis nudge', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jean',
        'last_name' => 'Luc',
        'phone' => '7195558814',
        'email' => 'jean.luc@example.test',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAinsightcall001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558814',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558814',
        'customer_id' => $customer->id,
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(18),
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Customer asked for brake quote by email.',
            'follow_up_needed' => true,
            'follow_up_notes' => 'Send brake quote email today.',
            'suggested_reply' => 'I will email the brake quote before we close today.',
        ],
        'analyzed_at' => now(),
    ]);

    // The call deep link resolves to the customer conversation (continuity
    // model) — the call insight surfaces there with the SMS composer target.
    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertSee('Mark call handled', false)
        ->assertSee('Call insight', false)
        ->assertSee('I will email the brake quote before we close today.', false)
        ->assertSee('Use in composer', false)
        ->assertDontSee('Call analysis', false);
});

test('dismissed mark handled nudge reveals call analysis nudge with draft', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jean',
        'last_name' => 'Luc',
        'phone' => '7195558815',
        'email' => 'jean.luc@example.test',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAdismisscall001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558815',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558815',
        'customer_id' => $customer->id,
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(16),
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Customer asked for brake quote by email.',
            'follow_up_needed' => true,
            'follow_up_notes' => 'Send brake quote email today.',
            'suggested_reply' => 'I will email the brake quote before we close today.',
        ],
        'analyzed_at' => now(),
    ]);

    $entityKey = 'call:'.$session->id;

    $this->actingAs($advisor)
        ->post(route('operations.communications.nudge.dismiss'), [
            'entity_key' => $entityKey,
            'nudge_key' => 'call.mark_handled',
            'section' => 'attention',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['call' => $session->id]));

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertSee('Follow-up suggested', false)
        ->assertSee('Call analysis', false)
        ->assertSee('Jean — I will email the brake quote before we close today.', false)
        ->assertSee('Log call note', false)
        ->assertDontSee('Mark call handled', false);
});

test('owner call intelligence surfaces suggested reply from sms analysis', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Text',
        'last_name' => 'Customer',
        'phone' => '7195558812',
        'email' => 'text@example.test',
    ]);

    $recorder = app(ConversationRecorder::class);
    $recorder->recordInboundSms('7195558812', 'Can I get a quote on brakes?', 'SM_intel_suggested', $customer);
    $recorder->recordOutboundSms($customer, $advisor, 'Yes — send a photo if you can.', 'SM_intel_suggested2');

    $conversation = Conversation::query()->where('contact_address', '7195558812')->firstOrFail();
    $slice = ConversationSmsIntelligenceSlice::query()->where('conversation_id', $conversation->id)->firstOrFail();
    $slice->forceFill([
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Advisor answered brake quote quickly.',
            'follow_up_needed' => true,
            'follow_up_notes' => 'Send quote after photo arrives.',
            'suggested_reply' => 'Thanks — once we see the photo we can finalize the quote.',
        ],
        'analyzed_at' => now(),
    ])->saveQuietly();

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence.sms.show', $slice))
        ->assertOk()
        ->assertSee('Suggested reply', false)
        ->assertSee('Thanks — once we see the photo we can finalize the quote.', false);
});
