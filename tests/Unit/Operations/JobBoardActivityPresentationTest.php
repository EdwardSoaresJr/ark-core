<?php

use App\Ark\Operations\Workboard\JobBoardActivityPresentation;
use App\Ark\Operations\Workboard\WorkboardCardActivityMark;
use App\Ark\Operations\Workboard\WorkboardCardActivityState;

test('job board activity presentation keeps idle rows empty', function () {
    $strip = JobBoardActivityPresentation::forList([
        new WorkboardCardActivityMark('estimate', 'EST', WorkboardCardActivityState::None, 'Estimate - None'),
        new WorkboardCardActivityMark('sms', 'SMS', WorkboardCardActivityState::None, 'SMS - None'),
    ]);

    expect($strip['visible'])->toBe([])
        ->and($strip['overflowCount'])->toBe(0)
        ->and($strip['overflowTooltip'])->toBeNull();
});

test('job board activity presentation prioritizes attention and caps visible marks', function () {
    $marks = [
        new WorkboardCardActivityMark('estimate', 'EST', WorkboardCardActivityState::Ready, 'Estimate - Ready'),
        new WorkboardCardActivityMark('sms', 'SMS', WorkboardCardActivityState::Engaged, 'SMS - Customer replied 12m ago', 'replied'),
        new WorkboardCardActivityMark('email', 'EMAIL', WorkboardCardActivityState::Sent, 'Email - Sent 2h ago'),
        new WorkboardCardActivityMark('phone', 'PHONE', WorkboardCardActivityState::Attention, 'Phone - Missed call 1h ago', 'alert'),
        new WorkboardCardActivityMark('dvi', 'DVI', WorkboardCardActivityState::Ready, 'DVI - On file 3h ago'),
        new WorkboardCardActivityMark('scheduled', 'APPT', WorkboardCardActivityState::Sent, 'Scheduled - Tomorrow 10:00 AM'),
    ];

    $strip = JobBoardActivityPresentation::forList($marks);

    expect(collect($strip['visible'])->pluck('key')->all())->toBe(['phone', 'sms', 'email', 'scheduled'])
        ->and($strip['overflowCount'])->toBe(2)
        ->and(collect($strip['overflow'])->pluck('key')->all())->toBe(['estimate', 'dvi'])
        ->and($strip['overflowTooltip'])->toStartWith('+2 more:')
        ->and($strip['overflowTooltip'])->toContain('Estimate - Ready')
        ->and($strip['overflowTooltip'])->toContain('DVI - On file');
});

test('job board activity destinations use verified RO workspace anchors', function () {
    expect(JobBoardActivityPresentation::destinationFor('estimate', '/app/repair-orders/89'))
        ->toBe('/app/repair-orders/89#builder')
        ->and(JobBoardActivityPresentation::destinationFor('sms', '/app/repair-orders/89'))
        ->toBe('/app/repair-orders/89#comms')
        ->and(JobBoardActivityPresentation::destinationFor('email', '/app/repair-orders/89'))
        ->toBe('/app/repair-orders/89#comms')
        ->and(JobBoardActivityPresentation::destinationFor('phone', '/app/repair-orders/89'))
        ->toBe('/app/repair-orders/89#comms')
        ->and(JobBoardActivityPresentation::destinationFor('dvi', '/app/repair-orders/89'))
        ->toBe('/app/repair-orders/89#inspect')
        ->and(JobBoardActivityPresentation::destinationFor('scheduled', '/app/repair-orders/89'))
        ->toBeNull();
});
