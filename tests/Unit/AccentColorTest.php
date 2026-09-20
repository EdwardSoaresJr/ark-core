<?php

use App\Ark\Runtime\Preferences\AccentColor;

test('accent color normalizes hex values', function () {
    expect(AccentColor::normalize('#FF12B0'))->toBe('#ff12b0')
        ->and(AccentColor::normalize('ff12b0'))->toBe('#ff12b0')
        ->and(AccentColor::normalize('bad'))->toBeNull();
});

test('accent color html style attribute sets css variable', function () {
    expect(AccentColor::htmlStyleAttribute('#ff12b0'))
        ->toBe('--ops-accent-custom: #ff12b0;');
});
