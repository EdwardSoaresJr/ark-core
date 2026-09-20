<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Parts\CustomerPartDescriptionPresenter;

/**
 * Customer-facing line labels for estimate review — mirrors PDF/portal presentation.
 */
final class EstimateReviewLinePresenter
{
    public function __construct(
        private readonly CustomerPartDescriptionPresenter $partDescriptions,
    ) {}

    public function description(
        RepairOrderLine $line,
        RepairOrderConcern $concern,
        ?RepairOrderWorkGroup $workGroup = null,
    ): string {
        unset($concern);

        if ($line->type === RepairOrderLineType::Part) {
            return $this->partDescriptions->present($line);
        }

        if ($workGroup !== null && $line->type->isLabor() && $workGroup->relationLoaded('lines')) {
            $laborCount = LaborDescriptionPresentation::laborCountInGroup($workGroup->lines);
            if (LaborDescriptionPresentation::shouldSuppressWorksheetDescription(
                $line,
                $workGroup->title,
                $laborCount,
            )) {
                return LaborDescriptionPresentation::compactLaborSummary($line);
            }
        }

        return trim((string) $line->description);
    }
}
