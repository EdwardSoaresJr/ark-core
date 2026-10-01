<?php

use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceSliceTouch;

test('single outbound sms with transcript is eligible for advisor coaching', function () {
    $slice = new ConversationSmsIntelligenceSlice([
        'message_count' => 1,
        'inbound_count' => 0,
        'outbound_count' => 1,
        'transcript' => 'Advisor: Your estimate is ready: https://portal.example/go/abc',
    ]);

    expect(ConversationSmsIntelligenceSliceTouch::isEligible($slice))->toBeTrue();
});

test('inbound only sms is eligible because missing advisor reply is coaching signal', function () {
    $slice = new ConversationSmsIntelligenceSlice([
        'message_count' => 1,
        'inbound_count' => 1,
        'outbound_count' => 0,
        'transcript' => 'Customer: Can I get a quote on my alternator?',
    ]);

    expect(ConversationSmsIntelligenceSliceTouch::isEligible($slice))->toBeTrue();
});

test('sms slice without transcript is not eligible', function () {
    $slice = new ConversationSmsIntelligenceSlice([
        'message_count' => 1,
        'inbound_count' => 0,
        'outbound_count' => 1,
        'transcript' => null,
    ]);

    expect(ConversationSmsIntelligenceSliceTouch::isEligible($slice))->toBeFalse();
});

test('sms analysis follow up is stale when new messages arrive after analysis', function () {
    $slice = new ConversationSmsIntelligenceSlice;
    $slice->forceFill([
        'analysis_status' => \App\Ark\Operations\Telephony\CallSessionAnalysisStatus::Ready,
        'analysis_json' => ['follow_up_needed' => true],
        'analyzed_at' => now()->subHour(),
        'last_message_at' => now()->subMinutes(5),
    ]);

    expect($slice->analysisIsStale())->toBeTrue()
        ->and($slice->advisorFollowUpApplies())->toBeFalse();
});
