<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Runtime\Preferences\ScheduleBoardPreference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentScheduleBoardViewController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $view = ScheduleBoardView::parse((string) $request->string('view'));
        $user = $request->user();
        if ($user instanceof User) {
            ScheduleBoardPreference::persist($user, $view);
        }

        return redirect()->route('operations.appointments.index', array_filter([
            'day' => $request->string('day')->toString() ?: null,
            'view' => $view->value,
            'lens' => $request->filled('lens') ? (string) $request->string('lens') : null,
        ]));
    }
}
