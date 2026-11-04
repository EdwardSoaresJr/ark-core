@php
    /** @var array<string, mixed> $work */
    $lane = (string) ($work['lane'] ?? 'needs');
    $url = (string) ($work['url'] ?? '');
    $filter = (string) ($work['filter'] ?? $lane);
    $owner = (string) ($work['owner'] ?? 'everyone');
    $advisors = is_array($work['advisors'] ?? null) ? $work['advisors'] : [];
    $defaultDue = (string) ($work['default_due_at'] ?? '');
    $postureChangedAt = (string) ($work['posture_changed_at'] ?? '');
    $ownerId = $work['owned_by_user_id'] ?? null;
    $hidden = static fn (string $action): string => '<input type="hidden" name="action" value="'.e($action).'">'
        .'<input type="hidden" name="filter" value="'.e($filter).'">'
        .'<input type="hidden" name="owner" value="'.e($owner).'">'
        .($postureChangedAt !== '' ? '<input type="hidden" name="posture_changed_at" value="'.e($postureChangedAt).'">' : '');
@endphp

{{-- The conversation's work controls, in one place: assign, follow up, wait, resolve or reopen. --}}
<div class="btn-list" role="group" aria-label="Conversation work" data-comms-work-actions>
    @if ($lane === 'resolved')
        <form method="POST" action="{{ $url }}" class="d-inline-flex">
            @csrf
            {!! $hidden('reopen') !!}
            <button type="submit" class="btn btn-sm">
                <x-tabler.icon name="arrow-left" />
                Reopen
            </button>
        </form>
    @else
        <div class="dropdown">
            <button type="button" class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Assign" title="Assign">
                <x-tabler.icon name="user" />
                <span class="d-none d-sm-inline">Assign</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 16rem">
                <form method="POST" action="{{ $url }}">
                    @csrf
                    {!! $hidden('assign') !!}
                    <input type="hidden" name="assign_to" value="me">
                    <button type="submit" class="btn btn-sm w-100 mb-2">Assign to me</button>
                </form>
                @if ($ownerId !== null)
                    <form method="POST" action="{{ $url }}">
                        @csrf
                        {!! $hidden('assign') !!}
                        <input type="hidden" name="assign_to" value="unassign">
                        <button type="submit" class="btn btn-sm w-100 mb-2" data-comms-work-unassign>Unassign</button>
                    </form>
                @endif
                <form method="POST" action="{{ $url }}">
                    @csrf
                    {!! $hidden('assign') !!}
                    <input type="hidden" name="assign_to" value="user">
                    <label class="form-label small mb-1" for="comms-work-assign">Assign to</label>
                    <div class="input-group input-group-sm">
                        <select id="comms-work-assign" name="user_id" class="form-select" required>
                            <option value="" disabled @selected($ownerId === null)>Choose…</option>
                            @foreach ($advisors as $advisor)
                                <option value="{{ $advisor['id'] }}" @selected((int) $ownerId === (int) $advisor['id'])>{{ $advisor['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn">Assign</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="dropdown">
            <button type="button" class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Follow-up" title="Follow-up">
                <x-tabler.icon name="calendar-time" />
                <span class="d-none d-sm-inline">Follow-up</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 16rem">
                <form method="POST" action="{{ $url }}">
                    @csrf
                    {!! $hidden('follow_up') !!}
                    <label class="form-label small mb-1" for="comms-work-due">Follow up on</label>
                    <div class="input-group input-group-sm">
                        <input id="comms-work-due" type="datetime-local" name="due_at" value="{{ $defaultDue }}" required class="form-control">
                        <button type="submit" class="btn">Set</button>
                    </div>
                </form>
            </div>
        </div>
        @if ($lane === 'needs')
            <form method="POST" action="{{ $url }}" class="d-inline-flex">
                @csrf
                {!! $hidden('wait') !!}
                <button type="submit" class="btn btn-sm" title="Waiting on the customer" aria-label="Waiting">
                    <x-tabler.icon name="clock" />
                    <span class="d-none d-sm-inline">Waiting</span>
                </button>
            </form>
        @endif
        <form method="POST" action="{{ $url }}" class="d-inline-flex">
            @csrf
            {!! $hidden('unread') !!}
            <button type="submit" class="btn btn-sm" title="Mark unread" aria-label="Mark unread" data-comms-work-unread>
                <x-tabler.icon name="mail" />
                <span class="d-none d-xxl-inline">Unread</span>
            </button>
        </form>
        <form method="POST" action="{{ $url }}" class="d-inline-flex">
            @csrf
            {!! $hidden('resolve') !!}
            <button type="submit" class="btn btn-sm btn-success">
                <x-tabler.icon name="check" />
                Resolve
            </button>
        </form>
    @endif
</div>
