@php
    $direction = (string) ($event['direction'] ?? 'inbound');
    $bubbleClass = $direction === 'outbound'
        ? 'ops-comms-workspace__bubble--outbound'
        : 'ops-comms-workspace__bubble--inbound';
    $voicemailState = (string) ($event['voicemail_state'] ?? 'none');
    $recordingState = (string) ($event['recording_state'] ?? 'none');
    $showRecording = ($event['title'] ?? '') !== 'Voicemail';
@endphp

<article
    @class(['ops-comms-workspace__bubble', $bubbleClass])
    data-call-session="{{ $event['call_session_id'] }}"
>
    <p class="ops-comms-workspace__bubble-meta">
        {{ $event['title'] ?? 'Call' }}
        @if (filled($event['direction_label'] ?? null))
            · {{ $event['direction_label'] }}
        @endif
        @if (filled($event['occurred_at_label'] ?? null))
            · {{ $event['occurred_at_label'] }}
        @endif
        @if (filled($event['ended_at_label'] ?? null))
            · Ended {{ $event['ended_at_label'] }}
        @endif
        @if (filled($event['duration_label'] ?? null))
            · {{ $event['duration_label'] }}
        @endif
    </p>

    @if ($voicemailState === 'playable' && filled($event['voicemail_url'] ?? null))
        <p class="ops-comms-inbox__call-media">Voicemail</p>
        <audio controls preload="none" class="ops-comms-inbox__call-audio" src="{{ $event['voicemail_url'] }}"></audio>
    @elseif ($voicemailState === 'unavailable')
        <p class="ops-comms-inbox__call-media">Voicemail unavailable</p>
    @endif

    @if ($showRecording && $recordingState === 'playable' && filled($event['recording_url'] ?? null))
        <p class="ops-comms-inbox__call-media">Recording</p>
        <audio controls preload="none" class="ops-comms-inbox__call-audio" src="{{ $event['recording_url'] }}"></audio>
    @elseif ($showRecording && $recordingState === 'unavailable')
        <p class="ops-comms-inbox__call-media">Recording unavailable</p>
    @endif

    @if (! empty($event['can_mark_handled']) && filled($event['mark_handled_url'] ?? null))
        <form method="POST" action="{{ $event['mark_handled_url'] }}" class="ops-comms-inbox__call-media">
            @csrf
            <input type="hidden" name="filter" value="{{ $listFilter ?? 'needs' }}">
            <input type="hidden" name="owner" value="{{ $ownerFilter ?? 'everyone' }}">
            @if (filled($thread['platform_conversation_public_id'] ?? null))
                <input type="hidden" name="platform_conversation" value="{{ $thread['platform_conversation_public_id'] }}">
            @endif
            @if (filled($selected['conversation_id'] ?? null))
                <input type="hidden" name="conversation" value="{{ $selected['conversation_id'] }}">
            @endif
            <button type="submit" class="ops-comms-inbox__decision-btn">Mark handled</button>
        </form>
    @elseif (! empty($event['handled']))
        <p class="ops-comms-inbox__call-media">Handled</p>
    @endif
</article>
