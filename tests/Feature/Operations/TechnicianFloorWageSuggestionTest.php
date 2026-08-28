<?php

use App\Ark\Operations\Labor\TechnicianFloorWageSuggestion;

test('floor wage suggestion is editable config and does not rewrite stored rates', function () {
    $original = (int) config('technician_compensation.floor_wage_suggestion.amount_cents');

    config(['technician_compensation.floor_wage_suggestion.amount_cents' => 1516]);

    expect(TechnicianFloorWageSuggestion::amountCents())->toBe(1516)
        ->and(TechnicianFloorWageSuggestion::needsReview(1516))->toBeFalse()
        ->and(TechnicianFloorWageSuggestion::needsReview(1575))->toBeTrue()
        ->and(TechnicianFloorWageSuggestion::needsReview(null))->toBeFalse();

    config(['technician_compensation.floor_wage_suggestion.amount_cents' => 1575]);

    expect(TechnicianFloorWageSuggestion::amountCents())->toBe(1575)
        ->and(TechnicianFloorWageSuggestion::needsReview(1516))->toBeTrue();

    config(['technician_compensation.floor_wage_suggestion.amount_cents' => $original]);
});
