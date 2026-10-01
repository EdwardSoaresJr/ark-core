<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Collection;

final class StaffCoachingLogPresenter
{
    /**
     * @return Collection<int, User>
     */
    public function coachableStaff(): Collection
    {
        $roleValues = collect(ArkRole::staffAssignable())
            ->map(fn (ArkRole $role): string => $role->value)
            ->all();

        return User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleValues))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function defaultStaffUserIdForCall(CallSession $callSession): ?int
    {
        if ($callSession->owned_by_user_id !== null) {
            return $callSession->owned_by_user_id;
        }

        return $this->coachableStaff()->first()?->id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCallSession(CallSession $callSession): array
    {
        return StaffCoachingLog::query()
            ->with(['staffMember:id,name', 'recordedBy:id,name'])
            ->where('call_session_id', $callSession->id)
            ->orderByDesc('discussed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StaffCoachingLog $log): array => $this->present($log))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forStaffMember(User $staffMember, int $limit = 50): array
    {
        return StaffCoachingLog::query()
            ->with(['callSession.customer', 'recordedBy:id,name'])
            ->where('staff_user_id', $staffMember->id)
            ->orderByDesc('discussed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (StaffCoachingLog $log): array => $this->present($log, includeCall: true))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(StaffCoachingLog $log, bool $includeCall = false): array
    {
        $timezone = ShopDisplayTimezone::resolve();

        $row = [
            'id' => $log->id,
            'notes' => $log->notes,
            'staff_name' => $log->staffMember?->name,
            'staff_user_id' => $log->staff_user_id,
            'recorded_by_name' => $log->recordedBy?->name,
            'discussed_at_label' => $log->discussed_at->timezone($timezone)->format('M j, Y g:i A'),
        ];

        if ($includeCall && $log->callSession) {
            $call = $log->callSession;
            $row['call_session_id'] = $call->id;
            $row['call_started_at_label'] = $call->started_at?->timezone($timezone)->format('M j, Y g:i A');
            $row['call_summary'] = $call->analysisSummary();
            $row['call_url'] = route('operations.owner.call-intelligence.show', $call);
            $row['customer_name'] = $call->customer?->name;
        } elseif ($log->call_session_id !== null) {
            $row['call_session_id'] = $log->call_session_id;
            $row['call_url'] = route('operations.owner.call-intelligence.show', $log->call_session_id);
        }

        return $row;
    }
}
