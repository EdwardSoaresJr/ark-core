<?php

use App\Ark\Operations\PhoneNumber;

test('phone number display formats ten digit us numbers', function () {
    expect(PhoneNumber::display('7195550101'))->toBe('(719) 555-0101')
        ->and(PhoneNumber::display('719-555-0101'))->toBe('(719) 555-0101')
        ->and(PhoneNumber::display('(719) 555-0101'))->toBe('(719) 555-0101')
        ->and(PhoneNumber::display('+1 719 555 0101'))->toBe('(719) 555-0101');
});

test('phone number normalize stores ten digit us numbers', function () {
    expect(PhoneNumber::normalize('(719) 555-0101'))->toBe('7195550101')
        ->and(PhoneNumber::normalize('7195550199'))->toBe('7195550199');
});

