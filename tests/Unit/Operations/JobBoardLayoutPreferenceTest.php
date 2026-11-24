<?php

use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
use Illuminate\Http\Request;

test('job board layout preference defaults to auto without cookie or query', function () {
    $resolved = JobBoardLayoutPreference::resolve(Request::create('/app', 'GET'));

    expect($resolved['mode'])->toBe(JobBoardLayoutPreference::MODE_AUTO)
        ->and($resolved['layout'])->toBeNull()
        ->and($resolved['explicit'])->toBeFalse();
});

test('job board layout preference honors view query and cookie values', function () {
    $fromQuery = JobBoardLayoutPreference::resolve(Request::create('/app', 'GET', ['view' => 'cards']));
    expect($fromQuery['mode'])->toBe('cards')
        ->and($fromQuery['layout'])->toBe(JobBoardLayout::Cards)
        ->and($fromQuery['explicit'])->toBeTrue();

    $request = Request::create('/app', 'GET');
    $request->cookies->set(JobBoardLayoutPreference::COOKIE_NAME, 'list');
    $fromCookie = JobBoardLayoutPreference::resolve($request);

    expect($fromCookie['mode'])->toBe('list')
        ->and($fromCookie['layout'])->toBe(JobBoardLayout::List)
        ->and($fromCookie['explicit'])->toBeTrue();
});

test('legacy board view maps to cards', function () {
    expect(JobBoardLayout::parse('board'))->toBe(JobBoardLayout::Cards)
        ->and(JobBoardLayout::tryParse('board'))->toBe(JobBoardLayout::Cards);
});
