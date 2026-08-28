<?php

namespace App\Ark\Operations\Workstations;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateWorkstationController
{
    public function __invoke(Request $request, Workstation $workstation, UpdateWorkstationAction $update): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'location_label' => ['nullable', 'string', 'max:128'],
            'accepts_scheduled_work' => ['sometimes', 'boolean'],
        ]);

        // Communications station forms no longer send this flag — preserve bay posture.
        $acceptsScheduledWork = $request->exists('accepts_scheduled_work')
            ? $request->boolean('accepts_scheduled_work')
            : null;

        $update->execute(
            $workstation,
            $data['name'],
            $data['location_label'] ?? null,
            $acceptsScheduledWork,
        );

        return redirect()
            ->route('operations.shop.communications')
            ->with('status', $workstation->fresh()->name.' updated.');
    }
}
