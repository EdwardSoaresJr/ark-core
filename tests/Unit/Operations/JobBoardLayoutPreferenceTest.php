<?php

use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

test('job board layout preference defaults to cards without cookie query or user value', function () {
    $resolved = JobBoardLayoutPreference::resolve(Request::create('/app', 'GET'));

    expect($resolved['mode'])->toBe(JobBoardLayout::Cards->value)
        ->and($resolved['layout'])->toBe(JobBoardLayout::Cards)
        ->and($resolved['explicit'])->toBeFalse();
});

test('job board layout preference honors saved user value over cookie', function () {
    $user = User::factory()->create(['job_board_view' => 'list']);
    $request = Request::create('/app', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->cookies->set(JobBoardLayoutPreference::COOKIE_NAME, 'cards');

    $resolved = JobBoardLayoutPreference::resolve($request, $user);

    expect($resolved['mode'])->toBe('list')
        ->and($resolved['layout'])->toBe(JobBoardLayout::List)
        ->and($resolved['explicit'])->toBeTrue();
});

test('job board layout preference migrates legacy cookie into the user row', function () {
    $user = User::factory()->create(['job_board_view' => null]);
    $request = Request::create('/app', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->cookies->set(JobBoardLayoutPreference::COOKIE_NAME, 'list');

    $resolved = JobBoardLayoutPreference::resolve($request, $user);

    expect($resolved['mode'])->toBe('list')
        ->and($resolved['explicit'])->toBeTrue()
        ->and($user->fresh()->job_board_view)->toBe('list');
});

test('job board layout preference honors view query and persists for the user', function () {
    $user = User::factory()->create(['job_board_view' => null]);
    $request = Request::create('/app', 'GET', ['view' => 'list']);
    $request->setUserResolver(fn () => $user);

    $fromQuery = JobBoardLayoutPreference::resolve($request, $user);

    expect($fromQuery['mode'])->toBe('list')
        ->and($fromQuery['layout'])->toBe(JobBoardLayout::List)
        ->and($fromQuery['explicit'])->toBeTrue()
        ->and($user->fresh()->job_board_view)->toBe('list');
});

test('invalid stored job board view falls back to cards', function () {
    expect(Schema::hasColumn('users', JobBoardLayoutPreference::COLUMN))->toBeTrue();

    $user = User::factory()->create(['job_board_view' => 'kanban']);
    expect(JobBoardLayoutPreference::storedLayout($user))->toBeNull();

    $resolved = JobBoardLayoutPreference::resolve(Request::create('/app', 'GET'), $user);
    expect($resolved['layout'])->toBe(JobBoardLayout::Cards)
        ->and($resolved['explicit'])->toBeFalse();
});

test('legacy board view maps to cards', function () {
    expect(JobBoardLayout::parse('board'))->toBe(JobBoardLayout::Cards)
        ->and(JobBoardLayout::tryParse('board'))->toBe(JobBoardLayout::Cards)
        ->and(JobBoardLayout::default())->toBe(JobBoardLayout::Cards);
});
