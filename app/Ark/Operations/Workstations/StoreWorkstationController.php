<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Communications\Telephony\EnsureWorkstationTelephonyExtensionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreWorkstationController
{
    public function __invoke(
        Request $request,
        StoreWorkstationAction $store,
        EnsureWorkstationTelephonyExtensionAction $ensureExtension,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'location_label' => ['nullable', 'string', 'max:128'],
            'accepts_scheduled_work' => ['sometimes', 'boolean'],
        ]);

        $workstation = $store->execute(
            $data['name'],
            $data['location_label'] ?? null,
            $request->boolean('accepts_scheduled_work'),
        );

        $ensureExtension->execute($workstation);

        return redirect()
            ->route('operations.shop.communications')
            ->with('status', $workstation->name.' added.');
    }
}
