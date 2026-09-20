<?php

namespace App\Ark\Operations\Settings\Concerns;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    protected function normalizeShopIdentityInput(Request $request): void
    {
        if ($request->exists('website')) {
            $website = trim((string) $request->input('website'));
            if ($website !== '' && preg_match('#^[a-z][a-z0-9+.-]*://#i', $website) !== 1) {
                $website = 'https://'.$website;
            }

            $request->merge(['website' => $website !== '' ? $website : null]);
        }

        if ($request->exists('shop_timezone')) {
            $timezone = trim((string) $request->input('shop_timezone'));
            $request->merge([
                'shop_timezone' => $timezone !== '' ? $timezone : ShopSettings::INSTALL_DEFAULT_TIMEZONE,
            ]);
        }

        $this->normalizeWeeklyHoursClocks($request);
    }

    protected function normalizeWeeklyHoursClocks(Request $request): void
    {
        $flow = $request->input('telephony_call_flow');
        if (! is_array($flow) || ! isset($flow['weekly_hours']) || ! is_array($flow['weekly_hours'])) {
            return;
        }

        foreach ($flow['weekly_hours'] as $day => $hours) {
            if (! is_array($hours)) {
                continue;
            }

            foreach (['open' => '09:00', 'close' => '18:00'] as $key => $fallback) {
                if (! array_key_exists($key, $hours)) {
                    continue;
                }

                $flow['weekly_hours'][$day][$key] = $this->normalizeClockTime(
                    is_string($hours[$key]) ? $hours[$key] : null,
                    $fallback,
                );
            }
        }

        $request->merge(['telephony_call_flow' => $flow]);
    }

    protected function normalizeClockTime(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches) !== 1) {
            return $value;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour > 23 || $minute > 59) {
            return $value;
        }

        return sprintf('%02d:%02d', $hour, $minute);
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
