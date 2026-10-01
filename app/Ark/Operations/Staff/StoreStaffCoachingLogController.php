<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreStaffCoachingLogController
{
    public function __invoke(Request $request, CallSession $callSession): RedirectResponse
    {
        $coachRoleValues = collect(ArkRole::staffAssignable())
            ->map(fn (ArkRole $role): string => $role->value)
            ->all();

        $data = $request->validate([
            'staff_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'notes' => ['required', 'string', 'max:5000'],
            'discussed_at' => ['nullable', 'date'],
            'complete_follow_up' => ['nullable', 'boolean'],
        ]);

        abort_unless(
            User::query()
                ->whereKey($data['staff_user_id'])
                ->whereHas('roles', fn ($query) => $query->whereIn('name', $coachRoleValues))
                ->exists(),
            422,
            'Select an active team member.',
        );

        StaffCoachingLog::query()->create([
            'call_session_id' => $callSession->id,
            'staff_user_id' => (int) $data['staff_user_id'],
            'recorded_by_user_id' => (int) $request->user()->id,
            'notes' => trim($data['notes']),
            'discussed_at' => isset($data['discussed_at'])
                ? \Illuminate\Support\Carbon::parse($data['discussed_at'])
                : now(),
        ]);

        if ($request->boolean('complete_follow_up') && $callSession->coaching_follow_up_at !== null) {
            $callSession->forceFill(['coaching_follow_up_at' => null])->save();
        }

        return redirect()
            ->back()
            ->with('status', 'Coaching debrief saved to team member profile.');
    }
}
