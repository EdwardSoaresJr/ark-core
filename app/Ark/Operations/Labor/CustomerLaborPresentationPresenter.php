<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\RepairOrders\LaborDescriptionPresentation;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

/**
 * Presentation-only boundary for customer-facing labor and sublet lines.
 *
 * Internal labor operations remain authoritative in storage. Customer documents
 * show line descriptions unless a single Labor line duplicates its repair title.
 * Document type badges: Labor → Labor, Sublet → Service.
 */
final class CustomerLaborPresentationPresenter
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function presentLines(array $lines, array $context = []): array
    {
        $parentTitle = isset($context['work_group_title']) ? (string) $context['work_group_title'] : null;
        $laborCount = LaborDescriptionPresentation::laborCountInSnapshotLines($lines);
        $presented = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $presented[] = $this->presentLine($line, $parentTitle, $laborCount);
        }

        return $presented;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $context
     */
    public function reviewLineDescription(array $line, array $context = []): string
    {
        $parentTitle = isset($context['work_group_title']) ? (string) $context['work_group_title'] : null;
        $description = trim((string) ($line['description'] ?? ''));

        if (
            ($line['type'] ?? '') === RepairOrderLineType::Labor->value
            && LaborDescriptionPresentation::matchesParent($description, $parentTitle)
        ) {
            return '';
        }

        return $description;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function presentLine(array $line, ?string $parentTitle, int $laborCount): array
    {
        $type = RepairOrderLineType::tryFrom((string) ($line['type'] ?? ''));

        if ($type === RepairOrderLineType::Labor || $type === RepairOrderLineType::Sublet) {
            // Customer docs: labor stays Labor; sublet presents as Service.
            $line['type_label'] = $type->documentLabel();
        }

        if (LaborDescriptionPresentation::shouldSuppressCustomerDescription($line, $parentTitle, $laborCount)) {
            $line['description'] = '';
            $line['suppress_duplicate_description'] = true;
        }

        return $line;
    }
}
