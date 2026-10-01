<?php

use App\Ark\Operations\Telephony\TelephonyFeatureCodeDial;

test('recognizes standard asterisk feature code dials', function (string $dial): void {
    expect(TelephonyFeatureCodeDial::isFeatureCodeDial($dial))->toBeTrue();
})->with([
    '*43',
    '*97',
    '*72',
    '**101',
    '##',
    '*2',
]);

test('does not treat customer phone numbers as feature codes', function (string $dial): void {
    expect(TelephonyFeatureCodeDial::isFeatureCodeDial($dial))->toBeFalse();
})->with([
    '+17195551234',
    '7195551234',
    '101',
    '102',
    '',
]);
