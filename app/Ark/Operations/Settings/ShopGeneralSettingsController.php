<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Settings\ShopSettings;
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

        $callFlow = is_array($settings->telephony_call_flow) ? $settings->telephony_call_flow : ShopSettings::defaultTelephonyCallFlow();
        $callFlow['timezone'] = $data['shop_timezone'];

        $settings->update([
            'shop_name' => $data['shop_name'],
            'shop_timezone' => $data['shop_timezone'],
            'telephony_call_flow' => $callFlow,
            'phone' => $data['phone'],
            'email' => $data['email'],
            'website' => $data['website'],
            'logo_path' => $logoPath,
            'address_line_1' => $data['address_line_1'],
            'address_line_2' => $data['address_line_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
        ]);

        $this->syncOpenEstimateDocuments();

        ShopDisplayTimezone::apply();

        return $this->redirectWithStatus('General settings saved.');
    }
}
