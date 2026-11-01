@php
    $lane = (string) ($work['lane'] ?? 'needs');
    $url = (string) ($work['url'] ?? '');
    $filter = (string) ($work['filter'] ?? 'needs');
    $owner = (string) ($work['owner'] ?? 'everyone');
    $advisors = is_array($work['advisors'] ?? null) ? $work['advisors'] : [];
    $defaultDue = (string) ($work['default_due_at'] ?? '');
    $postureChangedAt = (string) ($work['posture_changed_at'] ?? '');
    $ownerId = $work['owned_by_user_id'] ?? null;
    $ownerName = (string) ($work['owner_name'] ?? 'Unassigned');
@endphp

<section class="p-3 border-bottom" aria-labelledby="comms-context-work" data-comms-work-actions>
    <div class="subheader mb-1" id="comms-context-work">Work actions</div>
    <div class="fw-bold mb-2">{{ $ownerName }}</div>

    <form method="POST" action="{{ $url }}" class="mb-2">
        @csrf
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="assign_to" value="user">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <input type="hidden" name="owner" value="{{ $owner }}">
        @if ($postureChangedAt !== '')
            <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
        @endif
        <label class="visually-hidden" for="comms-work-assign">Assign to</label>
        <div class="input-group input-group-sm">
            <select id="comms-work-assign" name="user_id" class="form-select" required>
                <option value="" disabled @selected($ownerId === null)>Assign to…</option>
                @foreach ($advisors as $advisor)
                    <option value="{{ $advisor['id'] }}" @selected((int) $ownerId === (int) $advisor['id'])>{{ $advisor['name'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn">Assign to…</button>
        </div>
    </form>

    @if ($lane !== 'resolved')
        <form method="POST" action="{{ $url }}" class="mb-2">
            @csrf
            <input type="hidden" name="action" value="follow_up">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="hidden" name="owner" value="{{ $owner }}">
            @if ($postureChangedAt !== '')
                <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
            @endif
            <label class="visually-hidden" for="comms-work-due">Follow-up</label>
            <div class="input-group input-group-sm">
                <input id="comms-work-due" type="datetime-local" name="due_at" value="{{ $defaultDue }}" required class="form-control">
                <button type="submit" class="btn">Set follow-up</button>
            </div>
        </form>
        <div class="btn-list">
            <form method="POST" action="{{ $url }}" class="d-inline-flex">
                @csrf
                <input type="hidden" name="action" value="wait">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <input type="hidden" name="owner" value="{{ $owner }}">
                @if ($postureChangedAt !== '')
                    <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
                @endif
                <button type="submit" class="btn btn-sm">Mark waiting</button>
            </form>
            <form method="POST" action="{{ $url }}" class="d-inline-flex">
                @csrf
                <input type="hidden" name="action" value="resolve">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <input type="hidden" name="owner" value="{{ $owner }}">
                @if ($postureChangedAt !== '')
                    <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
                @endif
                <button type="submit" class="btn btn-sm">Resolve conversation</button>
            </form>
        </div>
    @else
        <form method="POST" action="{{ $url }}">
            @csrf
            <input type="hidden" name="action" value="reopen">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="hidden" name="owner" value="{{ $owner }}">
            @if ($postureChangedAt !== '')
                <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
            @endif
            <button type="submit" class="btn btn-sm">Reopen</button>
        </form>
    @endif
</section>
