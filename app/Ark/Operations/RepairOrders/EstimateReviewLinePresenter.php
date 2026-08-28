<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Customer-facing line labels for estimate review — mirrors PDF/portal presentation.
 */
final class EstimateReviewLinePresenter
{
    public function description(
        RepairOrderLine $line,
        RepairOrderConcern $concern,
        ?RepairOrderWorkGroup $workGroup = null,
    ): string {
        unset($concern);

        $explicitCustomerDescription = trim((string) ($line->customer_description ?? ''));

        if ($line->type === RepairOrderLineType::Part && $explicitCustomerDescription !== '') {
            return $explicitCustomerDescription;
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
