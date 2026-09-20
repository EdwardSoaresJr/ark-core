<?php

use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Telephony\AnalyzeCallSessionJob;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Telephony\CallSessionAnalyzer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    ShopSettings::current()->persistTrusted([
        'learn_training_gate_enabled' => false,
    ]);
    $this->seed(ArkAuthorizationSeeder::class);
});

test('owner call intelligence page requires admin role', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.owner.call-intelligence'))
        ->assertForbidden();
});

test('multi role admins with bookend access can open call intelligence', function () {
    $admin = User::factory()->create()->assignRole([
        ArkRole::Admin->value,
        ArkRole::Advisor->value,
        ArkRole::Technician->value,
    ]);

    $this->actingAs($admin)
        ->get(route('operations.owner.day-review'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence'))
        ->assertOk()
        ->assertSee('SMS intelligence');
});

test('owner call intelligence lists most needed coaching queue first', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcoach001',
        'direction' => 'inbound',
        'from_number' => '+17195551111',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551111',
        'status' => 'completed',
        'started_at' => now()->subDays(2),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REcoach001',
        'recording_duration_seconds' => 120,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Light coaching only.',
            'coaching_priority' => 'low',
            'empathy_score' => 4,
            'coaching_improvements' => ['Confirm next step before ending call'],
        ],
        'analyzed_at' => now(),
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcoach002',
        'direction' => 'inbound',
        'from_number' => '+17195552222',
        'to_number' => '+17195559999',
        'normalized_from' => '7195552222',
        'status' => 'completed',
        'started_at' => now()->subDay(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REcoach002',
        'recording_duration_seconds' => 180,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'High coaching needed.',
            'coaching_priority' => 'high',
            'empathy_score' => 2,
            'missed_upsell' => true,
            'missed_upsell_notes' => 'Did not offer brake inspection.',
            'coaching_improvements' => ['Offer related inspection items when customer mentions vibration'],
        ],
        'analyzed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence'))
        ->assertOk()
        ->assertSee('Most needed coaching')
        ->assertSeeInOrder([
            '(719) 555-2222',
            'Offer related inspection items when customer mentions vibration',
            '(719) 555-1111',
        ]);
});

test('owner can pin a call for coaching follow-up and it sorts above higher urgency calls', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $highUrgency = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcoach003',
        'direction' => 'inbound',
        'from_number' => '+17195553333',
        'to_number' => '+17195559999',
        'normalized_from' => '7195553333',
        'status' => 'completed',
        'started_at' => now()->subHours(6),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REcoach003',
        'recording_duration_seconds' => 180,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'High coaching needed.',
            'coaching_priority' => 'high',
            'empathy_score' => 1,
            'coaching_improvements' => ['Urgent coaching item'],
        ],
        'analyzed_at' => now(),
    ]);

    $pinned = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcoach004',
        'direction' => 'inbound',
        'from_number' => '+17195554444',
        'to_number' => '+17195559999',
        'normalized_from' => '7195554444',
        'status' => 'completed',
        'started_at' => now()->subDays(3),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REcoach004',
        'recording_duration_seconds' => 90,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Owner wants to revisit this call.',
            'coaching_priority' => 'low',
            'coaching_improvements' => ['Pinned coaching follow-up'],
        ],
        'analyzed_at' => now(),
        'coaching_follow_up_at' => now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence'))
        ->assertOk()
        ->assertSeeInOrder([
            '(719) 555-4444',
            'Pinned coaching follow-up',
            '(719) 555-3333',
            'Urgent coaching item',
        ]);

    $this->actingAs($admin)
        ->post(route('operations.owner.call-intelligence.follow-up.toggle', $highUrgency))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($highUrgency->fresh()->coaching_follow_up_at)->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('operations.owner.call-intelligence.follow-up.toggle', $pinned))
        ->assertRedirect();

    expect($pinned->fresh()->coaching_follow_up_at)->toBeNull();

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence', ['analysis' => 'pinned']))
        ->assertOk()
        ->assertSee('(719) 555-3333')
        ->assertDontSee('(719) 555-4444');
});

test('owner can open coaching handout pdf', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CApdf001',
        'direction' => 'inbound',
        'from_number' => '+17195556666',
        'to_number' => '+17195559999',
        'normalized_from' => '7195556666',
        'status' => 'completed',
        'started_at' => now()->subDay(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REpdf001',
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Customer upset about wait time.',
            'sentiment' => 'frustrated',
            'customer_intent' => 'Check status of brake job.',
            'coaching_priority' => 'high',
            'empathy_score' => 2,
            'coaching_improvements' => ['Acknowledge frustration before explaining timeline'],
        ],
        'analyzed_at' => now(),
    ]);

    $pdf = Mockery::mock(\App\Ark\Operations\Documents\HtmlPdfBuilder::class);
    $pdf->shouldReceive('toPdfBytes')->once()->andReturn('%PDF-fake');
    app()->instance(\App\Ark\Operations\Documents\HtmlPdfBuilder::class, $pdf);

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence.coaching-pdf', $session))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="call-coaching-'.$session->id.'.pdf"');
});

test('coaching handout html separates customer and advisor sections', function () {
    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CApdf002',
        'direction' => 'inbound',
        'from_number' => '+17195557777',
        'to_number' => '+17195559999',
        'normalized_from' => '7195557777',
        'status' => 'completed',
        'started_at' => now()->subDay(),
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Customer asked about oil change timing.',
            'sentiment' => 'concerned',
            'customer_intent' => 'Schedule oil change this week.',
            'outcome' => 'Callback promised.',
            'empathy_score' => 3,
            'coaching_priority' => 'medium',
        ],
        'analyzed_at' => now(),
    ]);

    $sheet = app(\App\Ark\Operations\Telephony\CallCoachingSheetPresenter::class)->for($session);
    $html = view('operations.documents.sheets.call-coaching', ['sheet' => $sheet])->render();

    expect($html)
        ->toContain('coaching-band--customer')
        ->toContain('coaching-band--advisor')
        ->toContain('Schedule oil change this week.')
        ->toContain('Staff coaching handout');
});

test('advisor cannot pin coaching follow-up on a call', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcoach005',
        'direction' => 'inbound',
        'from_number' => '+17195555555',
        'to_number' => '+17195559999',
        'normalized_from' => '7195555555',
        'status' => 'completed',
        'started_at' => now(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REcoach005',
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => ['coaching_priority' => 'medium'],
        'analyzed_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.owner.call-intelligence.follow-up.toggle', $session))
        ->assertForbidden();
});

test('owner call intelligence lists calls with ai analysis', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'phone' => '7195551234',
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAintel001',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'completed',
        'customer_id' => $customer->id,
        'started_at' => now()->subDay(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/RE001',
        'recording_duration_seconds' => 95,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'transcript' => 'Customer asked about brake noise.',
        'analysis_json' => [
            'summary' => 'Customer reported front brake noise and asked about inspection timing.',
            'sentiment' => 'concerned',
            'follow_up_needed' => true,
            'follow_up_notes' => 'Send estimate once inspection is complete.',
            'missed_upsell' => true,
            'missed_upsell_notes' => 'Did not offer brake fluid or alignment check when customer mentioned vibration.',
            'empathy_score' => 4,
            'empathy_label' => 'Strong',
            'ownership_score' => 3,
            'clarity_score' => 4,
            'coaching_priority' => 'medium',
            'coaching_improvements' => ['Offer related inspection items when customer mentions vibration'],
            'topics' => ['brakes', 'inspection'],
        ],
        'analyzed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence'))
        ->assertOk()
        ->assertSee('SMS intelligence')
        ->assertSee('Jane Driver')
        ->assertSee('front brake noise')
        ->assertSee('Customer')
        ->assertSee('Advisor')
        ->assertSee('Needs follow-up')
        ->assertSee('Missed upsell')
        ->assertSee('Empathy 4/5')
        ->assertSee('Handout')
        ->assertSee('Open call');
});

test('owner can open a single call intelligence view', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAintel002',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'completed',
        'started_at' => now()->subDay(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/RE002',
        'recording_duration_seconds' => 95,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'transcript' => 'Full transcript text for the dedicated call view.',
        'analysis_json' => [
            'summary' => 'Customer asked about timing for brake inspection.',
            'sentiment' => 'neutral',
            'follow_up_needed' => false,
            'missed_upsell' => false,
            'empathy_score' => 3,
            'coaching_priority' => 'none',
            'topics' => ['brakes'],
        ],
        'analyzed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence.show', $session))
        ->assertOk()
        ->assertSee('Full transcript text for the dedicated call view.')
        ->assertSee('Transcript')
        ->assertSee('All communications');
});

test('call session analyzer skips when model provider is not configured', function () {
    bindFakeOutboundSms();

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAanalyze001',
        'direction' => 'inbound',
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'status' => 'completed',
        'started_at' => now(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REanalyze001',
        'recording_duration_seconds' => 40,
    ]);

    app(CallSessionAnalyzer::class)->analyze($session->fresh());

    $session->refresh();

    expect($session->analysis_status)->toBe(CallSessionAnalysisStatus::Skipped)
        ->and($session->analysis_error)->toBe('Model provider is not configured.')
        ->and(CallSessionAnalyzer::enabled())->toBeFalse();
});

test('core settings no longer expose call intelligence provider configuration', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'ark-cloud']))
        ->assertOk()
        ->assertDontSee('OpenAI API key')
        ->assertDontSee('name="openai_api_key"');

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'customer-messaging']))
        ->assertOk()
        ->assertDontSee('OpenAI API key')
        ->assertDontSee('name="openai_api_key"');
});

test('owner call intelligence lists analyzed sms threads', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Text',
        'last_name' => 'Customer',
        'phone' => '7195558888',
        'customer_type' => 'Retail',
    ]);

    $recorder = app(ConversationRecorder::class);
    $recorder->recordInboundSms('7195558888', 'Can I get a quote on brakes?', 'SM_intel_1', $customer);
    $recorder->recordOutboundSms($customer, $advisor, 'Yes — send a photo if you can.', 'SM_intel_2');

    $slice = ConversationSmsIntelligenceSlice::query()->first();

    expect($slice)->not->toBeNull();

    $slice->forceFill([
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Advisor answered brake quote quickly.',
            'coaching_priority' => 'low',
            'coaching_improvements' => ['Ask for vehicle year/make when quoting brakes'],
        ],
        'analyzed_at' => now(),
    ])->saveQuietly();

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence', ['channel' => 'sms', 'media' => '']))
        ->assertOk()
        ->assertSee('SMS intelligence')
        ->assertSee('Text Customer')
        ->assertSee('Advisor answered brake quote quickly.')
        ->assertSee('Open thread');

    $this->actingAs($admin)
        ->get(route('operations.owner.call-intelligence.sms.show', $slice))
        ->assertOk()
        ->assertSee('Can I get a quote on brakes?')
        ->assertSee('Yes — send a photo if you can.');
});
