@php
    $tone = (string) ($decision['tone'] ?? 'needs');
    $title = (string) ($decision['title'] ?? '');
    $age = trim((string) ($decision['age'] ?? ''));
    $excerpt = trim((string) ($decision['excerpt'] ?? ''));
    $prompt = trim((string) ($decision['prompt'] ?? ''));
    $lane = (string) ($decision['lane'] ?? 'needs');
    $work = is_array($decision['work'] ?? null) ? $decision['work'] : null;
@endphp

<section @class(['ops-comms-inbox__decision', 'ops-comms-inbox__decision--'.$tone]) aria-label="What to do next">
    <p class="ops-comms-inbox__decision-title">
        {{ $title }}
        @if ($age !== '' && $lane === 'needs')
            <span>{{ $age }}</span>
        @endif
    </p>
    @if ($excerpt !== '')
        <p class="ops-comms-inbox__decision-excerpt">{{ $excerpt }}</p>
    @endif
    @if ($prompt !== '')
        <p class="ops-comms-inbox__decision-next">{{ $prompt }}</p>
    @endif

    @if (filled($decision['check_in_url'] ?? null))
        <div class="ops-comms-inbox__decision-actions">
            <a href="{{ $decision['check_in_url'] }}" class="ops-comms-inbox__decision-btn ops-comms-inbox__decision-btn--primary">Check In</a>
        </div>
    @endif

    @if (is_array($work) && filled($work['url'] ?? null) && $lane !== 'resolved')
        <div class="ops-comms-inbox__decision-actions">
            <a href="#comms-thread-composer" class="ops-comms-inbox__decision-btn ops-comms-inbox__decision-btn--primary">Reply</a>
            <form method="POST" action="{{ $work['url'] }}" class="ops-comms-inbox__decision-form">
                @csrf
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="assign_to" value="me">
                <input type="hidden" name="filter" value="{{ $work['filter'] ?? $lane }}">
                <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
                @if (filled($work['posture_changed_at'] ?? null))
                    <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
                @endif
                <button type="submit" class="ops-comms-inbox__decision-btn">Assign</button>
            </form>
            <form method="POST" action="{{ $work['url'] }}" class="ops-comms-inbox__decision-form">
                @csrf
                <input type="hidden" name="action" value="follow_up">
                <input type="hidden" name="due_at" value="{{ $work['default_due_at'] ?? '' }}">
                <input type="hidden" name="filter" value="{{ $work['filter'] ?? $lane }}">
                <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
                @if (filled($work['posture_changed_at'] ?? null))
                    <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
                @endif
                <button type="submit" class="ops-comms-inbox__decision-btn">Follow-up</button>
            </form>
            @if ($lane === 'needs')
                <form method="POST" action="{{ $work['url'] }}" class="ops-comms-inbox__decision-form">
                    @csrf
                    <input type="hidden" name="action" value="wait">
                    <input type="hidden" name="filter" value="{{ $work['filter'] ?? $lane }}">
                    <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
                    @if (filled($work['posture_changed_at'] ?? null))
                        <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
                    @endif
                    <button type="submit" class="ops-comms-inbox__decision-btn">Mark waiting</button>
                </form>
            @endif
            <form method="POST" action="{{ $work['url'] }}" class="ops-comms-inbox__decision-form">
                @csrf
                <input type="hidden" name="action" value="resolve">
                <input type="hidden" name="filter" value="{{ $work['filter'] ?? $lane }}">
                <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
                @if (filled($work['posture_changed_at'] ?? null))
                    <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
                @endif
                <button type="submit" class="ops-comms-inbox__decision-btn">Resolve</button>
            </form>
        </div>
    @elseif (is_array($work) && filled($work['url'] ?? null))
        <form method="POST" action="{{ $work['url'] }}">
            @csrf
            <input type="hidden" name="action" value="reopen">
            <input type="hidden" name="filter" value="{{ $work['filter'] ?? 'resolved' }}">
            <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
            @if (filled($work['posture_changed_at'] ?? null))
                <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
            @endif
            <button type="submit" class="ops-comms-inbox__decision-btn">Reopen</button>
        </form>
    @endif
</section>
