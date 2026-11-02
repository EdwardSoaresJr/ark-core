@php
    $tone = (string) ($decision['tone'] ?? 'needs');
    $title = (string) ($decision['title'] ?? '');
    $age = trim((string) ($decision['age'] ?? ''));
    $excerpt = trim((string) ($decision['excerpt'] ?? ''));
    $prompt = trim((string) ($decision['prompt'] ?? ''));
    $lane = (string) ($decision['lane'] ?? 'needs');
    $work = is_array($decision['work'] ?? null) ? $decision['work'] : null;
    $alertTone = match ($tone) {
        'waiting' => 'alert-warning',
        'overdue' => 'alert-orange',
        'resolved' => 'alert-success',
        default => 'alert-danger',
    };
@endphp

<section class="alert {{ $alertTone }} m-2 mb-0 py-2" aria-label="What to do next" data-comms-decision="{{ $tone }}">
    <div class="fw-bold">
        {{ $title }}
        @if ($age !== '' && $lane === 'needs')
            <span class="fw-normal text-secondary">{{ $age }}</span>
        @endif
    </div>
    @if ($excerpt !== '')
        <div class="text-secondary text-truncate">{{ $excerpt }}</div>
    @endif
    @if ($prompt !== '')
        <div class="text-secondary">{{ $prompt }}</div>
    @endif

    @php
        $spamUrl = filled($decision['spam_url'] ?? null) ? (string) $decision['spam_url'] : '';
        $spamInWorkRow = $spamUrl !== '' && is_array($work) && filled($work['url'] ?? null) && $lane !== 'resolved';
    @endphp

    @if (filled($decision['check_in_url'] ?? null) || ($spamUrl !== '' && ! $spamInWorkRow))
        <div class="btn-list mt-2">
            @if (filled($decision['check_in_url'] ?? null))
                <a href="{{ $decision['check_in_url'] }}" class="btn btn-sm btn-primary">Check In</a>
            @endif
            @if ($spamUrl !== '' && ! $spamInWorkRow)
                @include('operations.communications.workspace.partials.decision-spam', ['spamUrl' => $spamUrl])
            @endif
        </div>
    @endif

    @if (is_array($work) && filled($work['url'] ?? null) && $lane !== 'resolved')
        <div class="btn-list mt-2">
            <a href="#comms-thread-composer" class="btn btn-sm btn-primary">Reply</a>
            <form method="POST" action="{{ $work['url'] }}" class="d-inline-flex">
                @csrf
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="assign_to" value="me">
                <input type="hidden" name="filter" value="{{ $work['filter'] ?? $lane }}">
                <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
                @if (filled($work['posture_changed_at'] ?? null))
                    <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
                @endif
                <button type="submit" class="btn btn-sm">Assign</button>
            </form>
            <form method="POST" action="{{ $work['url'] }}" class="d-inline-flex">
                @csrf
                <input type="hidden" name="action" value="follow_up">
                <input type="hidden" name="due_at" value="{{ $work['default_due_at'] ?? '' }}">
                <input type="hidden" name="filter" value="{{ $work['filter'] ?? $lane }}">
                <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
                @if (filled($work['posture_changed_at'] ?? null))
                    <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
                @endif
                <button type="submit" class="btn btn-sm">Follow-up</button>
            </form>
            @if ($lane === 'needs')
                <form method="POST" action="{{ $work['url'] }}" class="d-inline-flex">
                    @csrf
                    <input type="hidden" name="action" value="wait">
                    <input type="hidden" name="filter" value="{{ $work['filter'] ?? $lane }}">
                    <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
                    @if (filled($work['posture_changed_at'] ?? null))
                        <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
                    @endif
                    <button type="submit" class="btn btn-sm">Mark waiting</button>
                </form>
            @endif
            <form method="POST" action="{{ $work['url'] }}" class="d-inline-flex">
                @csrf
                <input type="hidden" name="action" value="resolve">
                <input type="hidden" name="filter" value="{{ $work['filter'] ?? $lane }}">
                <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
                @if (filled($work['posture_changed_at'] ?? null))
                    <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
                @endif
                <button type="submit" class="btn btn-sm">Resolve</button>
            </form>
            @if ($spamInWorkRow)
                @include('operations.communications.workspace.partials.decision-spam', ['spamUrl' => $spamUrl])
            @endif
        </div>
    @elseif (is_array($work) && filled($work['url'] ?? null))
        <form method="POST" action="{{ $work['url'] }}" class="mt-2">
            @csrf
            <input type="hidden" name="action" value="reopen">
            <input type="hidden" name="filter" value="{{ $work['filter'] ?? 'resolved' }}">
            <input type="hidden" name="owner" value="{{ $work['owner'] ?? 'everyone' }}">
            @if (filled($work['posture_changed_at'] ?? null))
                <input type="hidden" name="posture_changed_at" value="{{ $work['posture_changed_at'] }}">
            @endif
            <button type="submit" class="btn btn-sm">Reopen</button>
        </form>
    @endif
</section>
