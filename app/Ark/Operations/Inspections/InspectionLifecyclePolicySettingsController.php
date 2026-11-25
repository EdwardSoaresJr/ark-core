<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Shop-wide inspection obligation (Optional / Recommended / Required).
 * Does not change historical RO overrides or waivers.
 */
final class InspectionLifecyclePolicySettingsController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'inspection_lifecycle_policy' => ['required', Rule::enum(InspectionRequirement::class)],
        ]);

        $policy = InspectionRequirement::from($data['inspection_lifecycle_policy']);

        ShopSettings::current()->persistTrusted([
            InspectionRequirementResolver::SHOP_SETTING_KEY => $policy->value,
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'workflow',
                'workflow-tab' => 'inspections',
            ])
            ->with('status', 'Inspection policy saved: '.$policy->label().'.');
    }
}
