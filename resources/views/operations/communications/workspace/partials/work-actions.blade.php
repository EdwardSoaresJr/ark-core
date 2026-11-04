@php
    /** @var array<string, mixed> $work */
    // 'rail': labelled buttons in the context lane. 'menu': Resolve or Reopen plus an Actions menu, for the
    // conversation header when the context lane is not beside it (tablet and phone).
    $variant = ($variant ?? 'rail') === 'menu' ? 'menu' : 'rail';
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
    $assignId = 'comms-work-assign-'.$variant;
    $dueId = 'comms-work-due-'.$variant;
@endphp

@if ($variant === 'menu')
    {{-- Tablet and phone: the most common action stays one tap away; the rest are in a labelled menu. --}}
    <div class="btn-list flex-nowrap" role="group" aria-label="Conversation work" data-comms-work-actions="menu">
        @if ($lane === 'resolved')
            <form method="POST" action="{{ $url }}" class="d-inline-flex">
                @csrf
                {!! $hidden('reopen') !!}
                <button type="submit" class="btn btn-sm">
                    <x-tabler.icon name="arrow-left" />
                    <span class="ark-comms-action-label">Reopen</span>
                </button>
            </form>
        @else
            <form method="POST" action="{{ $url }}" class="d-inline-flex">
                @csrf
                {!! $hidden('resolve') !!}
                <button type="submit" class="btn btn-sm btn-success">
                    <x-tabler.icon name="check" />
                    <span class="ark-comms-action-label">Resolve</span>
                </button>
            </form>
            <div class="dropdown">
                <button type="button" class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Conversation actions" title="Conversation actions">
                    <x-tabler.icon name="dots" />
                    <span class="ark-comms-action-label">Actions</span>
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
                    <form method="POST" action="{{ $url }}" class="mb-2">
                        @csrf
                        {!! $hidden('assign') !!}
                        <input type="hidden" name="assign_to" value="user">
                        <label class="form-label small mb-1" for="{{ $assignId }}">Assign to</label>
                        <div class="input-group input-group-sm">
                            <select id="{{ $assignId }}" name="user_id" class="form-select" required>
                                <option value="" disabled @selected($ownerId === null)>Choose…</option>
                                @foreach ($advisors as $advisor)
                                    <option value="{{ $advisor['id'] }}" @selected((int) $ownerId === (int) $advisor['id'])>{{ $advisor['name'] }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn">Assign</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ $url }}">
                        @csrf
                        {!! $hidden('follow_up') !!}
                        <label class="form-label small mb-1" for="{{ $dueId }}">Follow up on</label>
                        <div class="input-group input-group-sm">
                            <input id="{{ $dueId }}" type="datetime-local" name="due_at" value="{{ $defaultDue }}" required class="form-control">
                            <button type="submit" class="btn">Set</button>
                        </div>
                    </form>
                    <div class="dropdown-divider"></div>
                    @if ($lane === 'needs')
                        <form method="POST" action="{{ $url }}">
                            @csrf
                            {!! $hidden('wait') !!}
                            <button type="submit" class="dropdown-item">
                                <x-tabler.icon name="clock" class="me-2" />
                                Waiting on the customer
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ $url }}">
                        @csrf
                        {!! $hidden('unread') !!}
                        <button type="submit" class="dropdown-item" data-comms-work-unread>
                            <x-tabler.icon name="mail" class="me-2" />
                            Mark unread
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
@else
    {{-- The context lane: every work control, labelled. --}}
    <div class="ark-comms-work-grid" role="group" aria-label="Conversation work" data-comms-work-actions="rail">
        @if ($lane === 'resolved')
            <form method="POST" action="{{ $url }}" class="d-flex">
                @csrf
                {!! $hidden('reopen') !!}
                <button type="submit" class="btn btn-sm w-100">
                    <x-tabler.icon name="arrow-left" />
                    Reopen
                </button>
            </form>
        @else
            <form method="POST" action="{{ $url }}" class="d-flex">
                @csrf
                {!! $hidden('resolve') !!}
                <button type="submit" class="btn btn-sm btn-success w-100">
                    <x-tabler.icon name="check" />
                    Resolve
                </button>
            </form>
            <div class="dropdown">
                <button type="button" class="btn btn-sm dropdown-toggle w-100" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
                    <x-tabler.icon name="user" />
                    Assign
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
                        <label class="form-label small mb-1" for="{{ $assignId }}">Assign to</label>
                        <div class="input-group input-group-sm">
                            <select id="{{ $assignId }}" name="user_id" class="form-select" required>
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
                <button type="button" class="btn btn-sm dropdown-toggle w-100" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
                    <x-tabler.icon name="calendar-time" />
                    Follow-up
                </button>
                <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 16rem">
                    <form method="POST" action="{{ $url }}">
                        @csrf
                        {!! $hidden('follow_up') !!}
                        <label class="form-label small mb-1" for="{{ $dueId }}">Follow up on</label>
                        <div class="input-group input-group-sm">
                            <input id="{{ $dueId }}" type="datetime-local" name="due_at" value="{{ $defaultDue }}" required class="form-control">
                            <button type="submit" class="btn">Set</button>
                        </div>
                    </form>
                </div>
            </div>
            @if ($lane === 'needs')
                <form method="POST" action="{{ $url }}" class="d-flex">
                    @csrf
                    {!! $hidden('wait') !!}
                    <button type="submit" class="btn btn-sm w-100" title="Waiting on the customer">
                        <x-tabler.icon name="clock" />
                        Waiting
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ $url }}" class="d-flex">
                @csrf
                {!! $hidden('unread') !!}
                <button type="submit" class="btn btn-sm w-100" data-comms-work-unread>
                    <x-tabler.icon name="mail" />
                    Mark unread
                </button>
            </form>
        @endif
    </div>
@endif
