<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkstationOperatorController
{
    public function lock(Request $request): RedirectResponse|JsonResponse
    {
        $request->session()->forget(WorkstationPresence::SESSION_LOCKED);

        if ($request->expectsJson()) {
            return response()->json(['locked' => false]);
        }

        return redirect()->back();
    }

    public function unlock(Request $request, UnlockWorkstationOperatorAction $unlock): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pin' => ['required', 'string', 'size:4'],
        ]);

        $presence = WorkstationPresence::resolve($request);
        $workstation = $presence->workstation;
        $binding = WorkstationPresence::resolveBinding($request);

        abort_unless($workstation !== null, 422, 'Bind this computer to a workstation first.');
        abort_unless($binding !== null, 422, 'Bind this computer to a workstation first.');

        $viewer = $request->user();
        abort_unless($viewer !== null, 403);

        $operator = User::query()->findOrFail($data['user_id']);
        $workstation = $unlock->execute(
            $workstation,
            $viewer,
            $operator,
            $data['pin'],
            $binding,
        );

        $workstation->loadMissing('primaryTelephonyExtension');

        $request->session()->forget(WorkstationPresence::SESSION_LOCKED);

        $operator = $workstation->currentOperator ?? $operator;

        if ($request->expectsJson()) {
            return response()->json([
                'operator' => [
                    'id' => $operator->id,
                    'name' => $operator->name,
                ],
                'workstation' => [
                    'id' => $workstation->id,
                    'name' => $workstation->applianceDisplayName(),
                    'station_label' => $workstation->applianceStationLabel(),
                ],
            ]);
        }

        return redirect()
            ->back()
            ->with('status', $operator->name.' is now at '.$workstation->applianceDisplayName().'.');
    }

    public function staff(Request $request, WorkstationOperatorEligibility $eligibility): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer !== null, 403);

        $binding = WorkstationPresence::resolveBinding($request);

        $candidates = $eligibility->unlockCandidates($viewer, $binding);

        $staff = $candidates
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'has_pin' => filled($user->operator_pin_hash),
                'initials' => $user->presenceAvatarInitials(),
                'avatar_color' => $user->presenceAvatarColor(),
            ])
            ->values();

        $suggestedUserId = $candidates->contains('id', $viewer->id)
            ? $viewer->id
            : null;

        return response()->json([
            'staff' => $staff,
            'suggested_user_id' => $suggestedUserId,
        ]);
    }

    public function storePin(
        Request $request,
        CreateWorkstationOperatorPinAction $createPin,
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $presence = WorkstationPresence::resolve($request);
        $workstation = $presence->workstation;
        $binding = WorkstationPresence::resolveBinding($request);

        abort_unless($workstation !== null, 422, 'Bind this computer to a workstation first.');
        abort_unless($binding !== null, 422, 'Bind this computer to a workstation first.');

        $createPin->execute($user, $data['password'], $data['pin'], $workstation, $binding);

        $workstation->loadMissing('primaryTelephonyExtension');

        $request->session()->forget(WorkstationPresence::SESSION_LOCKED);

        $user = $user->fresh();

        if ($request->expectsJson()) {
            return response()->json([
                'operator' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'workstation' => [
                    'id' => $workstation->id,
                    'name' => $workstation->applianceDisplayName(),
                    'station_label' => $workstation->applianceStationLabel(),
                ],
            ]);
        }

        return redirect()
            ->back()
            ->with('status', $workstation->applianceDisplayName().' is ready.');
    }

    public function updatePin(
        Request $request,
        UpdateWorkstationOperatorPinAction $updatePin,
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $updatePin->execute($user, $data['password'], $data['pin']);

        if ($request->expectsJson()) {
            return response()->json(['updated' => true]);
        }

        return redirect()
            ->back()
            ->with('status', 'Workstation PIN updated.');
    }
}
