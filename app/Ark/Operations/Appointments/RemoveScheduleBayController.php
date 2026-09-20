<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Workstations\UpdateWorkstationAction;
use App\Ark\Operations\Workstations\Workstation;
use Illuminate\Http\RedirectResponse;

class RemoveScheduleBayController
{
    public function __invoke(Workstation $workstation, UpdateWorkstationAction $update): RedirectResponse
    {
        abort_unless($workstation->accepts_scheduled_work, 404);

        $name = $workstation->name;

        $cleared = Appointment::query()
            ->where('workstation_id', $workstation->id)
            ->whereNot('status', AppointmentStatus::Canceled)
            ->update(['workstation_id' => null]);

        $update->execute(
            $workstation,
            $workstation->name,
            $workstation->location_label,
            acceptsScheduledWork: false,
        );

        $status = $name.' removed from capacity planning.';

        if ($cleared > 0) {
            $status .= ' '.$cleared.' appointment'.($cleared === 1 ? '' : 's').' moved to Unassigned.';
        }

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'operations'])
            ->with('status', $status);
    }
}
