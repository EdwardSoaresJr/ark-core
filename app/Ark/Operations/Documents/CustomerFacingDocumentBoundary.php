<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Labor\CustomerLaborPresentationPresenter;
use App\Ark\Operations\Parts\CustomerPartPresentationPresenter;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\Parts\CustomerPartPresentationProfileResolver;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

/**
 * Presentation-only boundary for customer-facing documents.
 *
 * Stored snapshots and database rows keep internal truth intact. This layer
 * adds customer-facing presentation fields and strips internal metadata at
 * render time for PDF, portal, email, and print.
 */
final class CustomerFacingDocumentBoundary
{
    public function __construct(
        private readonly CustomerPartPresentationPresenter $partPresentationPresenter,
        private readonly CustomerLaborPresentationPresenter $laborPresentationPresenter,
        private readonly CustomerPartPresentationProfileResolver $partPresentationProfileResolver,
        private readonly CustomerFacingEstimateStatus $estimateStatus,
    ) {}

    /** @var list<string> */
    private const LINE_LABOR_AUTHORITY_KEYS = [
        'labor_category_key',
        'labor_category_name',
        'labor_entered_hours',
        'labor_adjustment',
        'labor_adjustment_factor',
        'labor_adjustment_reason',
        'labor_billed_hours',
        'labor_hours_overridden',
        'labor_override_reason',
        'labor_minimum_applied',
        'labor_rate_cents',
        'labor_rate',
    ];

    /** @var list<string> */
    private const SETTINGS_LABOR_AUTHORITY_KEYS = [
        'labor_categories',
    ];

    /** @var list<string> */
    private const LINE_INVENTORY_KEYS = [
        'customer_description',
        'part_number',
        'vendor_name',
        'part_cost_cents',
        'part_cost',
        'matrix_suggested_price_cents',
        'pricing_mode',
        'pricing_matrix_key',
        'pricing_matrix_name',
        'matrix_applied',
        'sourcing_notes',
        'procurement_state',
        'procurement_state_label',
        'procurement_next_action',
    ];

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function sanitize(array $snapshot): array
    {
        $snapshot = $this->sanitizeSettings($snapshot);
        $snapshot = $this->sanitizeConcerns($snapshot);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function sanitizeSettings(array $snapshot): array
    {
        $settings = $snapshot['settings'] ?? null;

        if (! is_array($settings)) {
            return $snapshot;
        }

        foreach (self::SETTINGS_LABOR_AUTHORITY_KEYS as $key) {
            unset($settings[$key]);
        }

        $snapshot['settings'] = $settings;

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function sanitizeConcerns(array $snapshot): array
    {
        $concerns = $snapshot['concerns'] ?? null;

        if (! is_array($concerns)) {
            return $snapshot;
        }

        $concerns = array_values(array_filter(
            $concerns,
            function (mixed $concern): bool {
                if (! is_array($concern)) {
                    return false;
                }

                $disposition = RepairOrderConcernDisposition::fromStored((string) ($concern['disposition'] ?? ''));

                return $disposition?->visibleToCustomer() ?? false;
            },
        ));

        foreach ($concerns as $concernIndex => $concern) {
            if (! is_array($concern)) {
                continue;
            }

            unset($concern['notes']);

            $profile = $this->partPresentationProfileResolver->forConcern($snapshot, $concern);
            $concern['customer_part_presentation_profile'] = $profile->value;

            $lines = $concern['lines'] ?? null;

            if (is_array($lines)) {
                $lines = $this->presentCustomerLines($lines, $concern, $profile);
                $concern['lines'] = $lines;
            }

            $workGroups = $concern['work_groups'] ?? null;

            if (is_array($workGroups)) {
                foreach ($workGroups as $groupIndex => $workGroup) {
                    if (! is_array($workGroup)) {
                        continue;
                    }

                    $groupLines = $workGroup['lines'] ?? null;

                    if (! is_array($groupLines)) {
                        continue;
                    }

                    $groupLines = $this->presentCustomerLines($groupLines, [
                        ...$concern,
                        'work_group_title' => $workGroup['title'] ?? null,
                    ], $profile);

                    $workGroup['lines'] = $groupLines;
                    $workGroups[$groupIndex] = $workGroup;
                }

                $concern['work_groups'] = $workGroups;
            }

            $concerns[$concernIndex] = $concern;
        }

        $snapshot['concerns'] = $concerns;

        if (isset($snapshot['repair_order']) && is_array($snapshot['repair_order'])) {
            $snapshot['repair_order']['status_label'] = $this->estimateStatus->labelForSnapshot($snapshot);
        }

        return $snapshot;
    }

    /**
     * @param  list<mixed>  $lines
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function presentCustomerLines(array $lines, array $context, CustomerPartPresentationProfile $profile): array
    {
        $lines = $this->filterCustomerVisibleLines($lines);
        $lines = $this->laborPresentationPresenter->presentLines($lines, $context);

        foreach ($lines as $lineIndex => $line) {
            if (! is_array($line)) {
                continue;
            }

            $siblingPartDescriptions = $this->siblingPartDescriptionsFromSnapshotBatch($lines, $lineIndex);
            $lines[$lineIndex] = $this->sanitizeLine($line, $profile, $siblingPartDescriptions);
        }

        return $lines;
    }

    /**
     * @param  list<mixed>  $lines
     * @return list<string>
     */
    private function siblingPartDescriptionsFromSnapshotBatch(array $lines, int $currentIndex): array
    {
        $descriptions = [];

        foreach ($lines as $index => $line) {
            if ($index === $currentIndex || ! is_array($line)) {
                continue;
            }

            if (($line['type'] ?? '') !== RepairOrderLineType::Part->value) {
                continue;
            }

            $description = trim((string) ($line['description'] ?? ''));

            if ($description !== '') {
                $descriptions[] = $description;
            }
        }

        return $descriptions;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  list<string>  $siblingPartDescriptions
     * @return array<string, mixed>
     */
    private function sanitizeLine(
        array $line,
        CustomerPartPresentationProfile $profile,
        array $siblingPartDescriptions = [],
    ): array {
        foreach (self::LINE_LABOR_AUTHORITY_KEYS as $key) {
            unset($line[$key]);
        }

        if (($line['type'] ?? '') === RepairOrderLineType::Part->value) {
            $line = $this->partPresentationPresenter->presentLine($profile, $line, $siblingPartDescriptions);

            foreach (self::LINE_INVENTORY_KEYS as $key) {
                unset($line[$key]);
            }
        }

        unset(
            $line['is_private'],
            $line['visible_to_advisor'],
            $line['visible_to_technician'],
            $line['visible_to_customer'],
        );

        return $line;
    }

    /**
     * @param  list<mixed>  $lines
     * @return list<array<string, mixed>>
     */
    private function filterCustomerVisibleLines(array $lines): array
    {
        return array_values(array_filter(
            $lines,
            fn (mixed $line): bool => is_array($line) && ! $this->isPrivateNoteLine($line),
        ));
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function isPrivateNoteLine(array $line): bool
    {
        if (($line['type'] ?? '') !== RepairOrderLineType::Note->value) {
            return false;
        }

        if (array_key_exists('visible_to_customer', $line)) {
            return ! (bool) $line['visible_to_customer'];
        }

        return (bool) ($line['is_private'] ?? false);
    }
}
