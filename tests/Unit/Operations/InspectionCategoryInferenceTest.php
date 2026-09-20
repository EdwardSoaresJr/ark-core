<?php

use App\Ark\Operations\Inspections\InspectionCategoryInference;
use App\Ark\Operations\Inspections\InspectionItemCategory;

test('inspection category inference maps common finding titles', function () {
    expect(InspectionCategoryInference::fromLabel('Front brake pads'))->toBe(InspectionItemCategory::Brakes)
        ->and(InspectionCategoryInference::fromLabel('RF tire tread low'))->toBe(InspectionItemCategory::Tires)
        ->and(InspectionCategoryInference::fromLabel('Battery terminal corrosion'))->toBe(InspectionItemCategory::Battery)
        ->and(InspectionCategoryInference::fromLabel('Coolant leak'))->toBe(InspectionItemCategory::Fluids);
});
