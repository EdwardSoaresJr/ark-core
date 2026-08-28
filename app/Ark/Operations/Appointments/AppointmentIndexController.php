<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Runtime\Preferences\ScheduleBoardPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AppointmentIndexController
{
    public function __invoke(Request $request): View
    {
        $timezone = ShopDisplayTimezone::resolve();
        $day = $request->filled('day')
            ? Carbon::parse((string) $request->string('day'), $timezone)->startOfDay()
            : ShopDisplayTimezone::now()->startOfDay();

        $view = ScheduleBoardPreference::resolve($request)->value;
        $lens = DayLens::parse($request->filled('lens') ? (string) $request->string('lens') : null);

        $workspace = app(SchedulingWorkspaceProjection::class)->resolve(
            $day,
            $view,
            'agenda',
            $request->user(),
            false,
            $lens,
        );

        $requestDayStatus = app(AppointmentRequestAvailabilityProjection::class)
            ->dayStatus($day->toDateString());

        return view('operations.appointments.index', [
            'workspace' => $workspace,
            'requestDayStatus' => $requestDayStatus,
        ]);
    }
}
