<?php

use App\Ark\Operations\Customers\CustomerIdentityPressure;

test('customer identity pressure labels distinguish completeness levels', function () {
    expect(CustomerIdentityPressure::Complete->showsChip())->toBeFalse()
        ->and(CustomerIdentityPressure::Incomplete->showsChip())->toBeTrue()
        ->and(CustomerIdentityPressure::Critical->showsChip())->toBeTrue()
        ->and(CustomerIdentityPressure::Incomplete->label())->toBe('Customer info incomplete')
        ->and(CustomerIdentityPressure::Critical->label())->toBe('Contact info missing');
});

test('customer identity pressure chip tones match severity', function () {
    expect(CustomerIdentityPressure::Incomplete->chipTone())->toBe('incomplete')
        ->and(CustomerIdentityPressure::Critical->chipTone())->toBe('critical');
});
