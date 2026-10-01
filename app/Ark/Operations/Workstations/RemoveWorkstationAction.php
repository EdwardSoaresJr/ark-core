<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Operations\Telephony\TelephonyExtension;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RemoveWorkstationAction
{
    public function execute(Workstation $workstation): void
    {
        if ($workstation->devices()->exists()) {
            throw new InvalidArgumentException('Remove phones from this workstation before deleting it.');
        }

        DB::transaction(function () use ($workstation): void {
            TelephonyExtension::query()
                ->where('workstation_id', $workstation->id)
                ->delete();

            $workstation->delete();
        });
    }
}
