<section class="card" data-call-coaching-debrief>
    <div class="card-header">
        <div>
            <h3 class="card-title">Coaching debrief</h3>
            <div class="card-subtitle">Log what you discussed with the team member. Saved to their coaching profile - owner-only, not visible to advisors.</div>
        </div>
    </div>
    <div class="card-body p-2 p-md-3">
        <p class="small mb-3">
            <a href="{{ \App\Ark\Runtime\Ecosystem\EcosystemArkademyBridge::advisorIncomingCallsUrl() }}" target="_blank" rel="noopener noreferrer">Open ARKademy · incoming calls training</a>
        </p>

        <form method="POST" action="{{ route('operations.owner.call-intelligence.coaching-log.store', $row['id']) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="coaching-staff-user-id">Discussed with</label>
                <select id="coaching-staff-user-id" name="staff_user_id" required @class(['form-select', 'is-invalid' => $errors->has('staff_user_id')])>
                    @foreach ($coachingStaffOptions as $staffMember)
                        <option value="{{ $staffMember->id }}" @selected(old('staff_user_id', $defaultCoachingStaffUserId) == $staffMember->id)>
                            {{ $staffMember->name }}
                        </option>
                    @endforeach
                </select>
                @error('staff_user_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="coaching-notes">How coaching went</label>
                <textarea
                    id="coaching-notes"
                    name="notes"
                    rows="4"
                    required
                    maxlength="5000"
                    placeholder="Discussed empathy on brake call, agreed to confirm next steps before hanging up, will listen to next three calls together…"
                    @class(['form-control', 'is-invalid' => $errors->has('notes')])
                >{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @if ($row['coaching_follow_up_pinned'])
                <input type="hidden" name="complete_follow_up" value="0">
                <label class="form-check mb-3">
                    <input type="checkbox" name="complete_follow_up" value="1" checked class="form-check-input">
                    <span class="form-check-label">Clear pinned follow-up after saving</span>
                </label>
            @endif
            <div class="btn-list align-items-center">
                <button type="submit" class="btn btn-primary">Save coaching debrief</button>
                @if ($defaultCoachingStaffUserId)
                    <a href="{{ route('operations.owner.staff.coaching', $defaultCoachingStaffUserId) }}" class="btn btn-ghost-secondary">View team member coaching history</a>
                @endif
            </div>
        </form>
    </div>

    @if (count($coachingLogs) > 0)
        <ol class="list-group list-group-flush border-top" data-call-coaching-logs>
            @foreach ($coachingLogs as $log)
                <li class="list-group-item">
                    <div class="small text-secondary fw-medium">
                        {{ $log['discussed_at_label'] }}
                        · {{ $log['staff_name'] }}
                        @if ($log['recorded_by_name'])
                            · logged by {{ $log['recorded_by_name'] }}
                        @endif
                    </div>
                    <p class="mt-1 mb-0" style="white-space: pre-wrap">{{ $log['notes'] }}</p>
                    @if ($log['staff_user_id'])
                        <a href="{{ route('operations.owner.staff.coaching', $log['staff_user_id']) }}" class="d-inline-block small mt-1">All coaching for {{ $log['staff_name'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    @else
        <div class="card-footer small text-secondary">No coaching debrief logged for this call yet.</div>
    @endif
</section>
