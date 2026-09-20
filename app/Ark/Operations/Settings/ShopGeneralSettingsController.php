<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ShopGeneralSettingsController
{
    use Concerns\InteractsWithShopSettingsPersistence;
    public function __construct(
        private readonly EstimateDocumentService $estimateDocumentService,
        private readonly EstimateTotalsCalculator $estimateTotalsCalculator,
    ) {}

    protected function estimateDocuments(): EstimateDocumentService
    {
        return $this->estimateDocumentService;
    }

    protected function totalsCalculator(): EstimateTotalsCalculator
    {
        return $this->estimateTotalsCalculator;
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $this->normalizeShopIdentityInput($request);

        $data = $request->validate([
            'shop_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:64'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'shop_timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'telephony_call_flow' => ['nullable', 'array'],
            'telephony_call_flow.weekly_hours' => ['nullable', 'array'],
            'telephony_call_flow.weekly_hours.*.enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.weekly_hours.*.open' => ['nullable', 'date_format:H:i'],
            'telephony_call_flow.weekly_hours.*.close' => ['nullable', 'date_format:H:i'],
            'telephony_call_flow.closed_dates' => ['nullable', 'string'],
        ]);

        $settings = ShopSettings::current();
        $logoPath = $settings->logo_path;

        if ((bool) ($data['remove_logo'] ?? false) && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('logo')->store('shop-logos', 'public');
        }

        $callFlowInput = is_array($data['telephony_call_flow'] ?? null) ? $data['telephony_call_flow'] : [];
        $existingFlow = TelephonyCallFlowSettings::fromShopSettings($settings)->toArray();
        $weeklyInput = is_array($callFlowInput['weekly_hours'] ?? null) ? $callFlowInput['weekly_hours'] : [];

        foreach (TelephonyCallFlowSettings::WEEKDAYS as $day) {
            $dayInput = is_array($weeklyInput[$day] ?? null) ? $weeklyInput[$day] : [];
            $existingFlow['weekly_hours'][$day] = [
                'enabled' => filter_var($dayInput['enabled'] ?? $existingFlow['weekly_hours'][$day]['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'open' => $dayInput['open'] ?? $existingFlow['weekly_hours'][$day]['open'] ?? '09:00',
                'close' => $dayInput['close'] ?? $existingFlow['weekly_hours'][$day]['close'] ?? '18:00',
            ];
        }

        $closedDates = array_key_exists('closed_dates', $callFlowInput)
            ? collect(preg_split('/\r\n|\r|\n/', (string) $callFlowInput['closed_dates']) ?: [])
                ->map(fn (string $date): string => trim($date))
                ->filter(fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
                ->unique()
                ->sort()
                ->values()
                ->all()
            : ($existingFlow['closed_dates'] ?? []);

        $existingFlow['timezone'] = $data['shop_timezone'];
        $existingFlow['closed_dates'] = $closedDates;

        $settings->update([
            'shop_name' => $data['shop_name'] ?? $settings->shop_name,
            'shop_timezone' => $data['shop_timezone'],
            'telephony_call_flow' => $existingFlow,
            'phone' => $data['phone'] ?? $settings->phone,
            'email' => $data['email'] ?? $settings->email,
            'website' => $data['website'] ?? $settings->website,
            'logo_path' => $logoPath,
            'address_line_1' => $data['address_line_1'] ?? $settings->address_line_1,
            'address_line_2' => $data['address_line_2'] ?? $settings->address_line_2,
            'city' => $data['city'] ?? $settings->city,
            'state' => $data['state'] ?? $settings->state,
            'postal_code' => $data['postal_code'] ?? $settings->postal_code,
        ]);

        $this->syncOpenEstimateDocuments();

        ShopDisplayTimezone::apply();

        return $this->redirectWithStatus('General settings saved.');
    }
}
