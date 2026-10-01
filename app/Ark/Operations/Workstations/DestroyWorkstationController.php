<?php

namespace App\Ark\Operations\Workstations;

use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class DestroyWorkstationController
{
    public function __invoke(Workstation $workstation, RemoveWorkstationAction $remove): RedirectResponse
    {
        $name = $workstation->name;

        try {
            $remove->execute($workstation);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->back()
                ->withErrors(['workstation' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.shop.communications')
            ->with('status', $name.' removed.');
    }
}
