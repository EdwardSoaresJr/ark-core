<?php

namespace App\Ark\Operations\Work;

use Illuminate\Http\RedirectResponse;

class CustomerDecisionScheduleClearController
{
    public function __invoke(CustomerDecisionSchedule $schedule): RedirectResponse
    {
        if ($schedule->isActive()) {
            $schedule->forceFill(['cleared_at' => now()])->save();
        }

        return redirect()
            ->route('operations.index')
            ->with('status', 'Schedule cleared — decision back on Work.');
    }
}
