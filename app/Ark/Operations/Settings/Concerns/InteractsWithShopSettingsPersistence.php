<?php

namespace App\Ark\Operations\Settings\Concerns;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use Illuminate\Http\RedirectResponse;

trait InteractsWithShopSettingsPersistence
{
    protected function redirectWithStatus(string $status): RedirectResponse
    {
        return redirect()
            ->route('operations.settings.shop.edit')
            ->with('status', $status);
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    protected function mergeSecretField(array &$updates, string $field, ?string $submitted): void
    {
        if ($submitted !== null && trim($submitted) !== '') {
            $updates[$field] = trim($submitted);
        }
    }

    protected function nullableTrimmedString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function recalculateLivingRepairOrderTotals(): void
    {
        $this->totalsCalculator()->recalculateLivingRepairOrders();
    }

    protected function syncOpenEstimateDocuments(): void
    {
        $this->estimateDocuments()->refreshOpenDocumentsForShopSettingsChange();
    }

    abstract protected function estimateDocuments(): EstimateDocumentService;

    abstract protected function totalsCalculator(): EstimateTotalsCalculator;

}
