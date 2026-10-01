<?php

use App\Ark\Operations\Intake\IntakeWorkspaceSession;
use Illuminate\Http\Request;

test('intake workspace session validates workspace ids', function () {
    expect(IntakeWorkspaceSession::isValidId('service'))->toBeFalse()
        ->and(IntakeWorkspaceSession::isValidId('abc123'))->toBeTrue()
        ->and(IntakeWorkspaceSession::isValidId(''))->toBeFalse();
});

test('intake create keeps existing workspace id', function () {
    $request = Request::create('/app/intake?ws=abc123456789', 'GET');

    expect(IntakeWorkspaceSession::ensureOnRequest($request))->toBeNull()
        ->and(IntakeWorkspaceSession::idFromRequest($request))->toBe('abc123456789');
});
