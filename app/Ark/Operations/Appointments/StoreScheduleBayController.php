<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Workstations\StoreWorkstationAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreScheduleBayController
{
    public function __invoke(Request $request, StoreWorkstationAction $store): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'location_label' => ['nullable', 'string', 'max:128'],
        ]);

        $bay = $store->execute(
            $data['name'],
            $data['location_label'] ?? null,
            acceptsScheduledWork: true,
        );

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'operations'])
            ->with('status', $bay->name.' added as a bay.');
    }
}
