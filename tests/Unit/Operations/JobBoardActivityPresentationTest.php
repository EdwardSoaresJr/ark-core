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
        ->and($strip)->not->toHaveKey('overflowCount')
        ->and($strip)->not->toHaveKey('overflow');
});

test('job board activity presentation shows all active marks in fixed category order', function () {
    $marks = [
        new WorkboardCardActivityMark('scheduled', 'APPT', WorkboardCardActivityState::Sent, 'Scheduled - Tomorrow 10:00 AM'),
        new WorkboardCardActivityMark('phone', 'PHONE', WorkboardCardActivityState::Attention, 'Phone - Missed call 1h ago', 'alert'),
        new WorkboardCardActivityMark('estimate', 'EST', WorkboardCardActivityState::Ready, 'Estimate - Ready'),
        new WorkboardCardActivityMark('dvi', 'DVI', WorkboardCardActivityState::Ready, 'DVI - On file 3h ago'),
        new WorkboardCardActivityMark('sms', 'SMS', WorkboardCardActivityState::Engaged, 'SMS - Customer replied 12m ago', 'replied'),
        new WorkboardCardActivityMark('email', 'EMAIL', WorkboardCardActivityState::Sent, 'Email - Sent 2h ago'),
    ];

    $strip = JobBoardActivityPresentation::forList($marks);

    expect(collect($strip['visible'])->pluck('key')->all())
        ->toBe(['estimate', 'sms', 'email', 'phone', 'dvi', 'scheduled'])
        ->and(collect($strip['visible'])->pluck('state')->map->value->all())
        ->toBe(['ready', 'engaged', 'sent', 'attention', 'ready', 'sent']);
});

test('job board activity presentation hides inactive categories without inventing overflow', function () {
    $strip = JobBoardActivityPresentation::forList([
        new WorkboardCardActivityMark('estimate', 'EST', WorkboardCardActivityState::None, 'Estimate - None'),
        new WorkboardCardActivityMark('sms', 'SMS', WorkboardCardActivityState::Sent, 'SMS - Delivered'),
        new WorkboardCardActivityMark('email', 'EMAIL', WorkboardCardActivityState::Engaged, 'Email - Opened'),
        new WorkboardCardActivityMark('phone', 'PHONE', WorkboardCardActivityState::None, 'Phone - None'),
        new WorkboardCardActivityMark('dvi', 'DVI', WorkboardCardActivityState::Ready, 'DVI - On file'),
        new WorkboardCardActivityMark('scheduled', 'APPT', WorkboardCardActivityState::None, 'Scheduled - None'),
    ]);

    expect(collect($strip['visible'])->pluck('key')->all())->toBe(['sms', 'email', 'dvi']);
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
