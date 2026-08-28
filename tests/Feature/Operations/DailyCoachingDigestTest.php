<?php

use App\Ark\Operations\Communications\CommunicationReview;
use App\Ark\Operations\Communications\RecordCommunicationReviewFromCallAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Mail\DailyCoachingDigestMail;
use App\Models\User;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('communication review is recorded from call analysis', function () {
    $advisor = User::factory()->create(['name' => 'Ben Advisor']);
    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Customer',
        'phone' => '555-0101',
        'email' => 'jane@example.com',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAreview001',
        'direction' => 'inbound',
        'from_number' => '+17195551111',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551111',
        'status' => 'completed',
        'customer_id' => $customer->id,
        'owned_by_user_id' => $advisor->id,
        'started_at' => now(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REReview001',
        'recording_duration_seconds' => 120,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'summary' => 'Great appointment capture.',
            'coaching_priority' => 'low',
            'empathy_score' => 5,
            'ownership_score' => 5,
            'clarity_score' => 4,
            'coaching_strengths' => ['Acknowledged urgency immediately'],
            'coaching_improvements' => ['Confirm drop-off time aloud'],
        ],
        'analyzed_at' => now(),
    ]);

    $review = app(RecordCommunicationReviewFromCallAction::class)->execute($session);

    expect($review)->not->toBeNull()
        ->and($review->composite_score)->toBe(93)
        ->and($review->strengths)->toContain('Acknowledged urgency immediately')
        ->and($review->opportunities)->toContain('Confirm drop-off time aloud')
        ->and($review->advisor_user_id)->toBe($advisor->id);
});

test('daily coaching digest email uses coaching framing and picks strongest and opportunity calls', function () {
    Mail::fake();

    $admin = User::factory()->create(['email' => 'owner@demo-auto.test'])->assignRole(ArkRole::Admin->value);
    $strongAdvisor = User::factory()->create(['name' => 'Strong Advisor']);
    $coachAdvisor = User::factory()->create(['name' => 'Coach Advisor']);

    $today = OperationalReportDateScope::shopNow()->toDateString();

    ShopExcellenceTargets::persist(array_merge(ShopExcellenceTargets::current(), [
        'coaching_digest_enabled' => true,
        'coaching_digest_extra_emails' => ['ben@demo-auto.test'],
    ]));

    $strongSession = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstrong001',
        'direction' => 'inbound',
        'from_number' => '+17195551112',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551112',
        'status' => 'completed',
        'customer_id' => Customer::query()->create([
            'first_name' => 'Celebration',
            'last_name' => 'Customer',
            'phone' => '555-0102',
            'email' => 'celebration@example.com',
        ])->id,
        'owned_by_user_id' => $strongAdvisor->id,
        'started_at' => now(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REstrong001',
        'recording_duration_seconds' => 90,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'coaching_priority' => 'low',
            'empathy_score' => 5,
            'ownership_score' => 5,
            'clarity_score' => 5,
            'coaching_strengths' => ['Set clear next step before ending call'],
            'coaching_improvements' => [],
        ],
        'analyzed_at' => now(),
    ]);

    $coachSession = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAcoach001',
        'direction' => 'inbound',
        'from_number' => '+17195551113',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551113',
        'status' => 'completed',
        'customer_id' => Customer::query()->create([
            'first_name' => 'Self',
            'last_name' => 'Diagnose',
            'phone' => '555-0103',
            'email' => 'self@example.com',
        ])->id,
        'owned_by_user_id' => $coachAdvisor->id,
        'started_at' => now(),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/REcoach001',
        'recording_duration_seconds' => 150,
        'analysis_status' => CallSessionAnalysisStatus::Ready,
        'analysis_json' => [
            'coaching_priority' => 'high',
            'empathy_score' => 2,
            'ownership_score' => 2,
            'clarity_score' => 3,
            'missed_upsell' => true,
            'missed_upsell_notes' => 'Customer insisted on water pump; advisor argued diagnosis.',
            'coaching_strengths' => [],
            'coaching_improvements' => ['Focus on getting the vehicle into the shop'],
        ],
        'analyzed_at' => now(),
    ]);

    app(RecordCommunicationReviewFromCallAction::class)->execute($strongSession);
    app(RecordCommunicationReviewFromCallAction::class)->execute($coachSession);

    $this->artisan('communications:daily-coaching-digest', ['--date' => $today])
        ->assertSuccessful();

    Mail::assertSent(DailyCoachingDigestMail::class, 2);

    $digestMail = Mail::sent(DailyCoachingDigestMail::class)->first();

    expect($digestMail->digest['strongest_call']['customer_name'])->toBe('Celebration Customer')
        ->and($digestMail->digest['strongest_call']['advisor_name'])->toBe('Strong Advisor')
        ->and($digestMail->digest['strongest_call']['why_it_worked'])->toContain('Set clear next step')
        ->and($digestMail->digest['coaching_opportunity']['customer_name'])->toBe('Self Diagnose')
        ->and($digestMail->digest['coaching_opportunity']['what_to_improve'])->toContain('getting the vehicle into the shop');
});
