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

<div class="ops-comms-inbox__rail-card">
    <p class="ops-comms-inbox__rail-title">Work actions</p>
    <p class="ops-comms-inbox__rail-name">{{ $ownerName }}</p>

    <form method="POST" action="{{ $url }}" class="ops-comms-inbox__rail-form">
        @csrf
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="assign_to" value="user">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <input type="hidden" name="owner" value="{{ $owner }}">
        @if ($postureChangedAt !== '')
            <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
        @endif
        <label class="sr-only" for="comms-work-assign">Assign to</label>
        <select id="comms-work-assign" name="user_id" class="ops-comms-workspace__assign-select" required>
            <option value="" disabled @selected($ownerId === null)>Assign to…</option>
            @foreach ($advisors as $advisor)
                <option value="{{ $advisor['id'] }}" @selected((int) $ownerId === (int) $advisor['id'])>{{ $advisor['name'] }}</option>
            @endforeach
        </select>
        <button type="submit" class="ops-comms-inbox__rail-action">Assign to…</button>
    </form>

    @if ($lane !== 'resolved')
        <form method="POST" action="{{ $url }}" class="ops-comms-inbox__rail-form">
            @csrf
            <input type="hidden" name="action" value="follow_up">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="hidden" name="owner" value="{{ $owner }}">
            @if ($postureChangedAt !== '')
                <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
            @endif
            <label class="sr-only" for="comms-work-due">Follow-up</label>
            <input id="comms-work-due" type="datetime-local" name="due_at" value="{{ $defaultDue }}" required class="ops-comms-workspace__assign-select">
            <button type="submit" class="ops-comms-inbox__rail-action">Set follow-up</button>
        </form>
        <form method="POST" action="{{ $url }}" class="ops-comms-inbox__rail-form">
            @csrf
            <input type="hidden" name="action" value="wait">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="hidden" name="owner" value="{{ $owner }}">
            @if ($postureChangedAt !== '')
                <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
            @endif
            <button type="submit" class="ops-comms-inbox__rail-action">Mark waiting</button>
        </form>
        <form method="POST" action="{{ $url }}" class="ops-comms-inbox__rail-form">
            @csrf
            <input type="hidden" name="action" value="resolve">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="hidden" name="owner" value="{{ $owner }}">
            @if ($postureChangedAt !== '')
                <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
            @endif
            <button type="submit" class="ops-comms-inbox__rail-action">Resolve conversation</button>
        </form>
    @else
        <form method="POST" action="{{ $url }}" class="ops-comms-inbox__rail-form">
            @csrf
            <input type="hidden" name="action" value="reopen">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="hidden" name="owner" value="{{ $owner }}">
            @if ($postureChangedAt !== '')
                <input type="hidden" name="posture_changed_at" value="{{ $postureChangedAt }}">
            @endif
            <button type="submit" class="ops-comms-inbox__rail-action">Reopen</button>
        </form>
    @endif
</div>
