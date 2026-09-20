<?php

use App\Ark\Operations\Telephony\CallSessionAnalysisProjection;

test('call session analysis projection normalizes coaching fields', function () {
    $projected = CallSessionAnalysisProjection::fromDecoded([
        'summary' => 'Test summary',
        'sentiment' => 'frustrated',
        'missed_upsell' => true,
        'missed_upsell_notes' => 'No inspection offered',
        'empathy_score' => 2.6,
        'ownership_score' => 9,
        'clarity_score' => 0,
        'coaching_priority' => 'HIGH',
        'coaching_strengths' => ['Good greeting', '', 'Listened'],
        'coaching_improvements' => ['Offer next step'],
        'topics' => ['brakes'],
    ]);

    expect($projected['sentiment'])->toBe('frustrated')
        ->and($projected['missed_upsell'])->toBeTrue()
        ->and($projected['empathy_score'])->toBe(3)
        ->and($projected['ownership_score'])->toBe(5)
        ->and($projected['clarity_score'])->toBe(1)
        ->and($projected['coaching_priority'])->toBe('high')
        ->and($projected['coaching_strengths'])->toBe(['Good greeting', 'Listened'])
        ->and($projected['suggested_reply'])->toBeNull();
});

test('call session analysis projection preserves suggested reply', function () {
    $projected = CallSessionAnalysisProjection::fromDecoded([
        'summary' => 'Customer asked about Monday drop-off.',
        'follow_up_needed' => true,
        'follow_up_notes' => 'Advisor missed confirming the visit.',
        'suggested_reply' => 'Monday works great — see you then!',
    ]);

    expect($projected['suggested_reply'])->toBe('Monday works great — see you then!')
        ->and($projected['follow_up_notes'])->toBe('Advisor missed confirming the visit.');
});

test('sentiment labels describe customer mood in plain language', function () {
    expect(CallSessionAnalysisProjection::sentimentLabel('frustrated'))->toBe('Frustrated / angry')
        ->and(CallSessionAnalysisProjection::sentimentLabel('concerned'))->toBe('Concerned')
        ->and(CallSessionAnalysisProjection::sentimentLabel('positive'))->toBe('Positive mood');
});

test('coaching urgency weight ranks high priority and weak empathy above light coaching', function () {
    $highNeed = CallSessionAnalysisProjection::coachingUrgencyWeight([
        'coaching_priority' => 'high',
        'empathy_score' => 2,
        'missed_upsell' => true,
        'appointment_captured' => false,
    ]);

    $mediumNeed = CallSessionAnalysisProjection::coachingUrgencyWeight([
        'coaching_priority' => 'medium',
        'empathy_score' => 3,
        'missed_upsell' => false,
    ]);

    $lowNeed = CallSessionAnalysisProjection::coachingUrgencyWeight([
        'coaching_priority' => 'low',
        'empathy_score' => 4,
    ]);

    expect($highNeed)->toBeGreaterThan($mediumNeed)
        ->and($mediumNeed)->toBeGreaterThan($lowNeed)
        ->and(CallSessionAnalysisProjection::coachingUrgencyWeight([
            'coaching_priority' => 'none',
        ]))->toBe(0);
});
