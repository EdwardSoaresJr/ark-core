<?php

namespace App\Ark\Operations\RepairOrders\Status;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderStatusCatalogSettingsController
{
    public function __invoke(Request $request, RepairOrderStatusCatalogUpdater $updater): RedirectResponse
    {
        $data = $request->validate([
            'create' => ['nullable', 'array'],
            'create.name' => ['nullable', 'string', 'max:64'],
            'create.slug' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/'],
            'create.advisor_lane_key' => ['nullable', 'string', 'max:32'],
            'create.color' => ['nullable', 'string', Rule::in(RepairOrderStatusColor::keys())],
            'create.show_on_advisor_board' => ['nullable'],
            'create.show_on_technician_board' => ['nullable'],
            'statuses' => ['nullable', 'array'],
            'statuses.*.name' => ['nullable', 'string', 'max:64'],
            'statuses.*.color' => ['nullable', 'string', Rule::in(RepairOrderStatusColor::keys())],
            'statuses.*.advisor_lane_key' => ['nullable', 'string', 'max:32'],
            'statuses.*.show_on_advisor_board' => ['nullable'],
            'statuses.*.show_on_technician_board' => ['nullable'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['nullable', 'string', 'max:64'],
            'transitions' => ['nullable', 'array'],
            'transitions.*.active' => ['nullable'],
            'transitions.*.roles' => ['nullable', 'array'],
            'transitions.*.roles.*' => ['string', 'max:32'],
            'create_transition' => ['nullable', 'array'],
            'create_transition.from_slug' => ['nullable', 'string', 'max:32'],
            'create_transition.to_slug' => ['nullable', 'string', 'max:32'],
            'create_transition.roles' => ['nullable', 'array'],
            'create_transition.roles.*' => ['string', 'max:32'],
        ]);

        $updater->apply($data);

        $message = match (true) {
            filled(trim((string) ($data['create']['name'] ?? ''))) => 'Custom status added and catalog updated.',
            filled(trim((string) ($data['create_transition']['from_slug'] ?? ''))) => 'Lifecycle move added and catalog updated.',
            default => 'Repair order status catalog updated.',
        };

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'workflow',
                'workflow-tab' => 'statuses',
            ])
            ->with('status', $message);
    }
}
