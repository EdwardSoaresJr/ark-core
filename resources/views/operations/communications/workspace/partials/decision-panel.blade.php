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

<section class="alert {{ $alertTone }} m-2 mb-0 py-2 small" aria-label="What to do next" data-comms-decision="{{ $tone }}">
    <div class="fw-bold">
        {{ $title }}
        @if ($age !== '' && $lane === 'needs')
            <span class="fw-normal text-secondary">{{ $age }}</span>
        @endif
    </div>
    @if ($excerpt !== '')
        <div class="text-secondary text-truncate d-none d-md-block">{{ $excerpt }}</div>
    @endif
    @if ($prompt !== '')
        <div class="text-secondary d-none d-md-block">{{ $prompt }}</div>
    @endif

    @php
        $spamUrl = filled($decision['spam_url'] ?? null) ? (string) $decision['spam_url'] : '';
        // Assign, follow-up, waiting and resolve live in the conversation header (work-actions).
        $spamInWorkRow = false;
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

</section>
