<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class TechnicianProductionAssistController
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);

        return view('operations.owner.technician-production.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => TechnicianProductionAssistProjection::shopSummaries($from, $to),
            'recognitionStartsAt' => TechnicianProductionAssistProjection::recognitionAuthorityStartsAt()->toDateString(),
            'northStar' => 'Why is this technician\'s recognized flag low — pending production still sitting, or not much production?',
        ]);
    }

    public function show(Request $request, User $user): View
    {
        abort_unless($user->worksAsTechnician(), 404);

        [$from, $to] = $this->resolveRange($request);
        $assist = TechnicianProductionAssistProjection::forTechnician($user, $from, $to);

        abort_unless($assist['applies'], 404);

        $timeByDate = [];
        foreach ($assist['detail']['daily_time'] as $day) {
            $timeByDate[$day['date']] = $day['compensable_hours'];
        }

        return view('operations.owner.technician-production.show', [
            'technician' => $user,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'assist' => $assist['detail'],
            'timeByDate' => $timeByDate,
            'weekDates' => $this->datesInRange($from, $to),
        ]);
    }

    public function storeTime(
        Request $request,
        User $user,
        UpsertTechnicianCompensableWeekAction $upsert,
    ): RedirectResponse {
        abort_unless($user->worksAsTechnician(), 404);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'hours' => ['required', 'array'],
            'hours.*' => ['nullable', 'numeric', 'min:0', 'max:24'],
        ]);

        $upsert->handle($user, $data['hours'], $request->user());

        return redirect()
            ->route('operations.owner.technician-production.show', [
                'user' => $user,
                'from' => $data['from'],
                'to' => $data['to'],
            ])
            ->with('status', 'Compensable hours saved for '.$user->name.'.');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        if (! filled($fromInput) || ! filled($toInput)) {
            return TechnicianProductionAssistProjection::defaultWeekRange(
                OperationalReportDateScope::shopNow(),
            );
        }

        [$from, $to] = OperationalReportDateScope::resolveRange($fromInput, $toInput);

        return [$from->copy()->startOfDay(), $to->copy()->startOfDay()];
    }

    /**
     * @return list<string>
     */
    private function datesInRange(Carbon $from, Carbon $to): array
    {
        $dates = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
