<?php

namespace App\Ark\Operations\Labor;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class TechnicianTimeClockController
{
    public function index(
        Request $request,
        MarkOvernightOpenSessionsAction $markOvernight,
        EnsureAutoClockSessionsAction $ensureAuto,
    ): View {
        $user = $request->user();
        abort_unless($user !== null && TechnicianTimeClockProjection::canAccess($user), 403);

        $canManage = TechnicianTimeClockProjection::canManage($user);
        $canPunchForStaff = TechnicianTimeClockProjection::canPunchForStaff($user);
        $canSelfPunch = TechnicianTimeClockProjection::canSelfPunch($user);

        $markOvernight->handle();
        $ensureAuto->handle();

        return view('operations.time-clock.index', [
            'projection' => $canSelfPunch ? TechnicianTimeClockProjection::forTechnician($user) : null,
            'canManage' => $canManage,
            'canPunchForStaff' => $canPunchForStaff,
            'needsResolution' => ($canManage || $canPunchForStaff)
                ? TechnicianTimeClockProjection::needsResolutionRows()
                : collect(),
            'staffTechnicians' => ($canManage || $canPunchForStaff)
                ? TechnicianTimeClockProjection::staffForClockList()
                : collect(),
        ]);
    }

    public function clockIn(Request $request, ClockInTechnicianAction $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && TechnicianTimeClockProjection::canSelfPunch($user), 403);

        try {
            $action->handle($user, $user);
        } catch (Throwable $exception) {
            return back()->withErrors(['time_clock' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.time-clock.index')
            ->with('status', 'Clocked in.');
    }

    public function clockOut(Request $request, ClockOutTechnicianAction $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && TechnicianTimeClockProjection::canSelfPunch($user), 403);

        $lunch = $request->boolean('lunch');

        try {
            $action->handle($user, $user, $lunch ? TechnicianTimeSessionCloseReason::Lunch : null);
        } catch (Throwable $exception) {
            return back()->withErrors(['time_clock' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.time-clock.index')
            ->with('status', $lunch ? 'Out for lunch.' : 'Clocked out.');
    }

    public function clockInForStaff(
        Request $request,
        User $user,
        ClockInTechnicianAction $action,
    ): RedirectResponse {
        abort_unless(TechnicianTimeClockProjection::canPunchForStaff($request->user()), 403);
        abort_unless(TechnicianTimeClockProjection::canBeClocked($user), 404);

        try {
            $action->handle($user, $request->user());
        } catch (Throwable $exception) {
            return back()->withErrors(['time_clock' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.time-clock.staff', $user)
            ->with('status', $user->name.' clocked in.');
    }

    public function clockOutForStaff(
        Request $request,
        User $user,
        ClockOutTechnicianAction $action,
    ): RedirectResponse {
        abort_unless(TechnicianTimeClockProjection::canPunchForStaff($request->user()), 403);
        abort_unless(TechnicianTimeClockProjection::canBeClocked($user), 404);

        $lunch = $request->boolean('lunch');

        try {
            $action->handle($user, $request->user(), $lunch ? TechnicianTimeSessionCloseReason::Lunch : null);
        } catch (Throwable $exception) {
            return back()->withErrors(['time_clock' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.time-clock.staff', $user)
            ->with('status', $user->name.($lunch ? ' out for lunch.' : ' clocked out.'));
    }

    public function showStaff(Request $request, User $user, EnsureAutoClockSessionsAction $ensureAuto): View
    {
        $actor = $request->user();
        $canManage = TechnicianTimeClockProjection::canManage($actor);
        $canPunchForStaff = TechnicianTimeClockProjection::canPunchForStaff($actor);
        abort_unless($canManage || $canPunchForStaff, 403);
        abort_unless(TechnicianTimeClockProjection::canBeClocked($user), 404);

        $ensureAuto->handleForUser($user);

        $sessions = TechnicianTimeSession::query()
            ->with(['corrections.correctedBy', 'clockedInBy', 'clockedOutBy'])
            ->where('user_id', $user->id)
            ->orderByDesc('clocked_in_at')
            ->limit(60)
            ->get();

        return view('operations.time-clock.staff', [
            'technician' => $user,
            'sessions' => $sessions,
            'projection' => TechnicianTimeClockProjection::forTechnician($user),
            'canManage' => $canManage,
            'canPunchForStaff' => $canPunchForStaff,
        ]);
    }

    public function updateAutoClock(
        Request $request,
        User $user,
        UpdateTechnicianAutoClockPolicyAction $action,
    ): RedirectResponse {
        abort_unless(TechnicianTimeClockProjection::canManage($request->user()), 403);
        abort_unless(TechnicianTimeClockProjection::canBeClocked($user), 404);

        $data = $request->validate([
            'auto_clock_enabled' => ['sometimes', 'boolean'],
            'auto_lunch_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
        ]);

        try {
            $action->handle(
                $user,
                (bool) ($data['auto_clock_enabled'] ?? false),
                isset($data['auto_lunch_minutes']) ? (int) $data['auto_lunch_minutes'] : null,
                $request->user(),
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['time_clock' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.time-clock.staff', $user)
            ->with('status', 'Auto clock updated for '.$user->name.'.');
    }

    public function correct(
        Request $request,
        TechnicianTimeSession $technicianTimeSession,
        CorrectTechnicianTimeSessionAction $action,
    ): RedirectResponse {
        abort_unless(TechnicianTimeClockProjection::canManage($request->user()), 403);

        $technicianTimeSession->loadMissing('technician');
        abort_unless($technicianTimeSession->technician !== null, 404);

        $data = $request->validate([
            'clocked_in_at' => ['nullable', 'string'],
            'clocked_out_at' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $action->handle(
                $technicianTimeSession,
                $data['clocked_in_at'] ?? null,
                $data['clocked_out_at'] ?? null,
                $data['reason'],
                $request->user(),
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['time_clock' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.time-clock.staff', $technicianTimeSession->technician)
            ->with('status', 'Punch corrected.');
    }

    public function destroy(
        Request $request,
        TechnicianTimeSession $technicianTimeSession,
        DeleteTechnicianTimeSessionAction $action,
    ): RedirectResponse {
        abort_unless(TechnicianTimeClockProjection::canManage($request->user()), 403);

        $technicianTimeSession->loadMissing('technician');
        abort_unless($technicianTimeSession->technician !== null, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $action->handle(
                $technicianTimeSession,
                $data['reason'],
                $request->user(),
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['time_clock' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.time-clock.staff', $technicianTimeSession->technician)
            ->with('status', 'Punch deleted.');
    }
}
