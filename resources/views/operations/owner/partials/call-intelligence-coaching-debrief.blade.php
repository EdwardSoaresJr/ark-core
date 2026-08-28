<div class="rounded-sm border border-slate-300 bg-slate-50/80">
    <div class="border-b border-slate-200 px-3 py-2">
        <h2 class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Coaching debrief</h2>
        <p class="mt-0.5 text-xs text-slate-600">Log what you discussed with the team member. Saved to their coaching profile — owner-only, not visible to advisors.</p>
        <p class="mt-1 text-xs text-slate-500">
            <a
                href="{{ \App\Ark\Runtime\Ecosystem\EcosystemArkademyBridge::advisorIncomingCallsUrl() }}"
                target="_blank"
                rel="noopener noreferrer"
                class="font-bold text-slate-600 underline decoration-slate-300 hover:text-slate-900"
            >Open ARKademy · incoming calls training</a>
        </p>
    </div>

    <form method="POST" action="{{ route('operations.owner.call-intelligence.coaching-log.store', $row['id']) }}" class="space-y-3 border-b border-slate-200 px-3 py-3">
        @csrf
        <label class="block text-xs">
            <span class="font-bold uppercase tracking-wide text-slate-500">Discussed with</span>
            <select name="staff_user_id" required class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                @foreach ($coachingStaffOptions as $staffMember)
                    <option value="{{ $staffMember->id }}" @selected(old('staff_user_id', $defaultCoachingStaffUserId) == $staffMember->id)>
                        {{ $staffMember->name }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs">
            <span class="font-bold uppercase tracking-wide text-slate-500">How coaching went</span>
            <textarea
                name="notes"
                rows="4"
                required
                maxlength="5000"
                placeholder="Discussed empathy on brake call, agreed to confirm next steps before hanging up, will listen to next three calls together…"
                class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm leading-5 text-slate-950"
            >{{ old('notes') }}</textarea>
        </label>
        @if ($row['coaching_follow_up_pinned'])
            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                <input type="hidden" name="complete_follow_up" value="0">
                <input type="checkbox" name="complete_follow_up" value="1" checked class="rounded-sm border-slate-300">
                Clear pinned follow-up after saving
            </label>
        @endif
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="rounded-sm border border-slate-800 bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">Save coaching debrief</button>
            @if ($defaultCoachingStaffUserId)
                <a
                    href="{{ route('operations.owner.staff.coaching', $defaultCoachingStaffUserId) }}"
                    class="text-xs font-bold text-slate-600 underline decoration-slate-300 hover:text-slate-900"
                >View team member coaching history</a>
            @endif
        </div>
    </form>

    @if (count($coachingLogs) > 0)
        <ol class="divide-y divide-slate-200 px-3 py-1">
            @foreach ($coachingLogs as $log)
                <li class="py-3">
                    <p class="text-[11px] font-bold text-slate-500">
                        {{ $log['discussed_at_label'] }}
                        · {{ $log['staff_name'] }}
                        @if ($log['recorded_by_name'])
                            · logged by {{ $log['recorded_by_name'] }}
                        @endif
                    </p>
                    <p class="mt-1 text-sm leading-5 text-slate-800 whitespace-pre-wrap">{{ $log['notes'] }}</p>
                    @if ($log['staff_user_id'])
                        <a
                            href="{{ route('operations.owner.staff.coaching', $log['staff_user_id']) }}"
                            class="mt-1 inline-block text-[11px] font-bold text-slate-600 underline decoration-slate-300 hover:text-slate-900"
                        >All coaching for {{ $log['staff_name'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    @else
        <p class="px-3 py-3 text-xs text-slate-500">No coaching debrief logged for this call yet.</p>
    @endif
</div>
