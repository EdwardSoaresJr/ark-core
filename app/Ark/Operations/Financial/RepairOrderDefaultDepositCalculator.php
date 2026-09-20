<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Collection;

final class RepairOrderDefaultDepositCalculator
{
    public function forRepairOrder(RepairOrder $repairOrder, ?ShopSettings $settings = null): RepairOrderDefaultDepositResult
    {
        $settings ??= ShopSettings::current();

        if (! $settings->defaultDepositEnabled()) {
            return new RepairOrderDefaultDepositResult(
                enabled: false,
                totalCents: 0,
                partsCents: 0,
                diagnosticsCents: 0,
            );
        }

        $repairOrder->loadMissing(['lines.concern']);

        // Deposit is conversation prep across approved + recommended work — not the
        // Estimate Total billable set (which drops Recommended once anything is Approved).
        $candidateLines = $this->depositCandidateLines($repairOrder->lines);
        $diagnosticKeys = $settings->defaultDepositDiagnosticLaborCategoryKeys();

        $partsCents = 0;
        $diagnosticsCents = 0;
        $lines = [];

        foreach ($candidateLines as $line) {
            if ($this->isDepositPartLine($line, $settings)) {
                $partsCents += (int) $line->total_cents;
                $lines[] = $this->depositLine($line, 'part', 'Part');
            }

            if ($this->isDepositDiagnosticLine($line, $settings, $diagnosticKeys)) {
                $diagnosticsCents += (int) $line->total_cents;
                $lines[] = $this->depositLine(
                    $line,
                    'diagnostic',
                    $settings->laborCategoryByKey((string) $line->labor_category_key)['name'] ?? 'Diagnostic',
                );
            }
        }

        return new RepairOrderDefaultDepositResult(
            enabled: true,
            totalCents: $partsCents + $diagnosticsCents,
            partsCents: $partsCents,
            diagnosticsCents: $diagnosticsCents,
            lines: $lines,
        );
    }

    public function portalDepositCents(RepairOrder $repairOrder, int $approvedAmountCents, ?ShopSettings $settings = null): int
    {
        $result = $this->forRepairOrder($repairOrder, $settings);

        if (! $result->enabled || ! $result->hasAmount()) {
            return max(0, $approvedAmountCents);
        }

        if ($approvedAmountCents <= 0) {
            return $result->totalCents;
        }

        return min($result->totalCents, $approvedAmountCents);
    }

    /**
     * @return list<RepairOrderDefaultDepositLine>
     */
    public function workspaceLines(RepairOrder $repairOrder, ?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();

        if (! $settings->defaultDepositEnabled()) {
            return [];
        }

        $repairOrder->loadMissing(['lines.concern']);

        $candidateLines = $this->depositCandidateLines($repairOrder->lines);
        $diagnosticKeys = $settings->defaultDepositDiagnosticLaborCategoryKeys();
        $lines = [];

        foreach ($candidateLines as $line) {
            if ($this->isDepositPartLine($line, $settings)) {
                $lines[] = $this->depositLine($line, 'part', 'Part', includedByDefault: true);
            }

            if ($line->type === RepairOrderLineType::Labor) {
                $categoryKey = (string) ($line->labor_category_key ?? '');
                $categoryLabel = $settings->laborCategoryByKey($categoryKey)['name'] ?? 'Labor';
                $includedByDefault = $this->isDepositDiagnosticLine($line, $settings, $diagnosticKeys);

                $lines[] = $this->depositLine(
                    $line,
                    $includedByDefault ? 'diagnostic' : 'labor',
                    $categoryLabel,
                    includedByDefault: $includedByDefault,
                );
            }
        }

        return $lines;
    }

    /**
     * Lines eligible for deposit quoting: Approved + Recommended (open sell path).
     * Excludes Draft / Deferred / Declined — those are not deposit conversation.
     *
     * @param  Collection<int, RepairOrderLine>  $lines
     * @return Collection<int, RepairOrderLine>
     */
    public function depositCandidateLines(Collection $lines): Collection
    {
        return $lines
            ->filter(function (RepairOrderLine $line): bool {
                $disposition = $line->concern?->disposition;

                if ($disposition === null) {
                    return true;
                }

                return in_array($disposition, [
                    RepairOrderConcernDisposition::Approved,
                    RepairOrderConcernDisposition::Recommended,
                ], true);
            })
            ->values();
    }

    private function depositLine(
        RepairOrderLine $line,
        string $category,
        string $categoryLabel,
        bool $includedByDefault = true,
    ): RepairOrderDefaultDepositLine {
        $sellSubtotalCents = max(0, (int) $line->subtotal_cents - (int) $line->standing_discount_cents);

        return new RepairOrderDefaultDepositLine(
            lineId: (int) $line->id,
            description: (string) $line->description,
            category: $category,
            categoryLabel: $categoryLabel,
            sellSubtotalCents: $sellSubtotalCents,
            taxCents: (int) $line->tax_cents,
            shopFeeCents: (int) $line->shop_fee_cents,
            amountCents: (int) $line->total_cents,
            includedByDefault: $includedByDefault,
        );
    }

    private function isDepositPartLine(RepairOrderLine $line, ShopSettings $settings): bool
    {
        return $settings->defaultDepositIncludeParts()
            && $line->type === RepairOrderLineType::Part;
    }

    /**
     * @param  list<string>  $diagnosticKeys
     */
    private function isDepositDiagnosticLine(RepairOrderLine $line, ShopSettings $settings, array $diagnosticKeys): bool
    {
        if (! $settings->defaultDepositIncludeDiagnostics()) {
            return false;
        }

        if ($line->type !== RepairOrderLineType::Labor) {
            return false;
        }

        $concern = $line->concern;

        if ($concern?->recommendationIntent() !== RecommendationIntent::Diagnostic) {
            return false;
        }

        $categoryKey = (string) ($line->labor_category_key ?? '');

        return $categoryKey !== '' && in_array($categoryKey, $diagnosticKeys, true);
    }
}
