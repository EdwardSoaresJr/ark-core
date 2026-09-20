<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Attention\AdvisorNudgeResponse;
use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
        });

test('attention workspace shows mark handled nudge for unhandled completed call', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnudgecall001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559001',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(20),
        'answered_at' => now()->subMinutes(19),
        'ended_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertSee('Suggested next step', false)
        ->assertSee('Mark call handled', false)
        ->assertSee('Mark handled', false)
        ->assertSee(route('operations.communications.calls.mark-handled', $session), false);
});

test('advisor can dismiss attention nudge', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnudgecall002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559002',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559002',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(15),
    ]);

    $entityKey = 'call:'.$session->id;

    $this->actingAs($advisor)
        ->post(route('operations.communications.nudge.dismiss'), [
            'entity_key' => $entityKey,
            'nudge_key' => 'call.mark_handled',
            'section' => 'attention',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertSessionHas('status', 'Nudge dismissed.');

    expect(AdvisorNudgeResponse::query()->sole())
        ->response->toBe(AdvisorNudgeResponseKind::Dismissed)
        ->and(AdvisorNudgeResponse::query()->sole()->nudge_key)->toBe('call.mark_handled');

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertDontSee('Mark call handled', false);
});

test('advisor can mark call handled from nudge action and log response', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnudgecall003',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559003',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559003',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(12),
    ]);

    $entityKey = 'call:'.$session->id;

    $this->actingAs($advisor)
        ->post(route('operations.communications.calls.mark-handled', $session), [
            'nudge_key' => 'call.mark_handled',
            'entity_key' => $entityKey,
            'section' => 'attention',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url())
        ->assertSessionHas('status', 'Call marked handled.');

    expect($session->refresh()->worked_at)->not->toBeNull();

    expect(AdvisorNudgeResponse::query()->sole())
        ->response->toBe(AdvisorNudgeResponseKind::Acted)
        ->and(AdvisorNudgeResponse::query()->sole()->nudge_key)->toBe('call.mark_handled');
});

test('logging call note from analysis nudge records call analysis follow up response', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jean',
        'last_name' => 'Luc',
        'phone' => '7195559006',
        'email' => 'jean.luc@example.test',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnudgecall006',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559006',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559006',
        'customer_id' => $customer->id,
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(8),
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'follow_up_needed' => true,
            'suggested_reply' => 'I will email the brake quote before we close today.',
        ],
        'analyzed_at' => now(),
    ]);

    $entityKey = 'call:'.$session->id;

    $this->actingAs($advisor)
        ->post(route('operations.communications.calls.note', $session), [
            'body' => 'Jean — I will email the brake quote before we close today.',
            'section' => 'attention',
            'entity_key' => $entityKey,
            'nudge_key' => 'call.analysis_follow_up',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertSessionHas('status', 'Call note logged.');

    expect(AdvisorNudgeResponse::query()->sole())
        ->response->toBe(AdvisorNudgeResponseKind::Acted)
        ->and(AdvisorNudgeResponse::query()->sole()->nudge_key)->toBe('call.analysis_follow_up');
});

test('logging call note marks call handled and records nudge response', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnudgecall004',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559004',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559004',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(8),
    ]);

    $entityKey = 'call:'.$session->id;

    $this->actingAs($advisor)
        ->post(route('operations.communications.calls.note', $session), [
            'body' => 'Customer asked about brake quote — will text estimate link.',
            'section' => 'attention',
            'entity_key' => $entityKey,
            'nudge_key' => 'call.log_note',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertSessionHas('status', 'Call note logged.');

    expect($session->refresh()->worked_at)->not->toBeNull();

    expect(AdvisorNudgeResponse::query()->sole())
        ->response->toBe(AdvisorNudgeResponseKind::Acted)
        ->and(AdvisorNudgeResponse::query()->sole()->nudge_key)->toBe('call.log_note');
});

test('attention list shows call channel label for unhandled calls', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnudgecall005',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559005',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559005',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('>Call<', false);
});

test('dismissed nudge stays hidden for eight hours then reappears', function (): void {
    Carbon::setTestNow($now = now());

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnudgesuppress1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559030',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559030',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(10),
    ]);

    $entityKey = 'call:'.$session->id;

    $this->actingAs($advisor)
        ->post(route('operations.communications.nudge.dismiss'), [
            'entity_key' => $entityKey,
            'nudge_key' => 'call.mark_handled',
            'section' => 'attention',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertDontSee('Mark call handled', false);

    Carbon::setTestNow($now->copy()->addHours(7));
    $session->update(['started_at' => now()->subMinutes(10)]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertDontSee('Mark call handled', false);

    Carbon::setTestNow($now->copy()->addHours(9));
    $session->update(['started_at' => now()->subMinutes(10)]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertSee('Mark call handled', false);

    Carbon::setTestNow();
});

test('missed call shows mark handled nudge with higher priority than completed call analysis', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAnudgemissed001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559031',
        'to_number' => '+17195559999',
        'normalized_from' => '7195559031',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinutes(6),
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'follow_up_needed' => true,
            'follow_up_notes' => 'Return missed call about brakes.',
            'suggested_reply' => 'Sorry we missed you — still need help with the brakes?',
        ],
        'analyzed_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertSee('Mark call handled', false)
        ->assertSee('Missed', false)
        ->assertSee('Call insight', false)
        ->assertSee('Sorry we missed you — still need help with the brakes?', false);
});

test('sending sms analysis draft reply records acted response', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'phone' => '7195559032',
        'email' => 'maria@example.test',
    ]);

    app(ConversationRecorder::class)
        ->recordInboundSms('7195559032', 'Can I get an appointment tomorrow?', 'SMnudgeanalysis1', $customer);

    $conversation = Conversation::query()->where('contact_address', '7195559032')->firstOrFail();
    $conversation->update(['owned_by_user_id' => $advisor->id]);

    $slice = ConversationSmsIntelligenceSlice::query()
        ->where('conversation_id', $conversation->id)
        ->firstOrFail();
    $slice->forceFill([
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Customer asked about appointment availability.',
            'follow_up_needed' => true,
            'suggested_reply' => 'We have openings tomorrow morning if that works for you.',
        ],
        'analyzed_at' => now(),
    ])->saveQuietly();

    $entityKey = 'conversation:'.$conversation->id;
    bindFakeOutboundSms();

    
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    // Outbound SMS requires a known-capable line — seed instead of hitting lookups.twilio.com.
    \App\Ark\Operations\Messaging\PhoneSmsCapability::query()->create([
        'normalized_phone' => \App\Ark\Operations\PhoneNumber::normalize('7195559032'),
        'valid' => true,
        'line_type' => 'mobile',
        'carrier_name' => 'Test',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
        'raw_payload' => ['source' => 'test'],
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Hi Maria, We have openings tomorrow morning if that works for you.',
            'nudge_key' => 'conversation.sms_analysis_follow_up',
            'entity_key' => $entityKey,
        ])
        ->assertOk();

    expect(AdvisorNudgeResponse::query()->sole())
        ->response->toBe(AdvisorNudgeResponseKind::Acted)
        ->and(AdvisorNudgeResponse::query()->sole()->nudge_key)->toBe('conversation.sms_analysis_follow_up');
});

