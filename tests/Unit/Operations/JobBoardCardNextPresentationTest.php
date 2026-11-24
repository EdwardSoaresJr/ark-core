<?php

use App\Ark\Operations\Today\AdvisorHomeCardChip;
use App\Ark\Operations\Today\AdvisorHomeCardSurface;
use App\Ark\Operations\Workboard\JobBoardCardNextPresentation;

function nextPresentationSurface(
    ?string $nextMoveLabel = null,
    string $attention = 'normal',
    ?string $exceptionLabel = null,
): AdvisorHomeCardSurface {
    return new AdvisorHomeCardSurface(
        chip: new AdvisorHomeCardChip(label: 'Building Estimate', tone: 'move', statusColor: null),
        customerPhone: null,
        techInitials: null,
        promiseLabel: null,
        promiseTone: 'none',
        vehicleOnSite: true,
        laborProgress: null,
        customerHubUrl: null,
        textCustomerUrl: null,
        recordFindingUrl: null,
        nextMoveLabel: $nextMoveLabel,
        attention: $attention,
        exceptionLabel: $exceptionLabel,
    );
}

it('shows concrete nextMoveLabel as an action', function () {
    $result = JobBoardCardNextPresentation::forSurface(
        nextPresentationSurface(nextMoveLabel: 'Send estimate', attention: 'attention'),
    );

    expect($result)->toBe(['kind' => 'action', 'label' => 'Send estimate']);
});

it('rejects generic attention copy as a next action', function () {
    $result = JobBoardCardNextPresentation::forSurface(
        nextPresentationSurface(nextMoveLabel: 'Needs attention', attention: 'attention', exceptionLabel: 'Needs attention'),
    );

    expect($result['kind'])->toBe('none');
});

it('shows concrete exception warnings without inventing Next', function () {
    $result = JobBoardCardNextPresentation::forSurface(
        nextPresentationSurface(attention: 'critical', exceptionLabel: 'Promise overdue'),
    );

    expect($result)->toBe(['kind' => 'warning', 'label' => 'Promise overdue']);
});

it('keeps Watch understated', function () {
    $result = JobBoardCardNextPresentation::forSurface(
        nextPresentationSurface(attention: 'watch', exceptionLabel: 'Watch'),
    );

    expect($result)->toBe(['kind' => 'watch', 'label' => 'Watch']);
});

it('omits when there is no actionable next or warning', function () {
    $result = JobBoardCardNextPresentation::forSurface(
        nextPresentationSurface(nextMoveLabel: 'Review', attention: 'normal'),
    );

    expect($result)->toBe(['kind' => 'none', 'label' => null]);
});
