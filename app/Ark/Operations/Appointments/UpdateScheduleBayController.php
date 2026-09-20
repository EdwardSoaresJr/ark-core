<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Workstations\UpdateWorkstationAction;
use App\Ark\Operations\Workstations\Workstation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateScheduleBayController
{
    public function __invoke(
        Request $request,
        Workstation $workstation,
        UpdateWorkstationAction $update,
    ): RedirectResponse {
        abort_unless($workstation->accepts_scheduled_work, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'location_label' => ['nullable', 'string', 'max:128'],
        ]);

        $update->execute(
            $workstation,
            $data['name'],
            $data['location_label'] ?? null,
        );

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'operations'])
            ->with('status', $workstation->fresh()->name.' updated.');
    }
}
