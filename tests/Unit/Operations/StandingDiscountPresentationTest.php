<?php

use App\Ark\Operations\Financial\StandingDiscountPresentation;

test('standing discount label uses billing class name', function (): void {
    expect(StandingDiscountPresentation::label('Military', 1000))->toBe('Military Discount')
        ->and(StandingDiscountPresentation::label('Retail', 0))->toBeNull();
});
