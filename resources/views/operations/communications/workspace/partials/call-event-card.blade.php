@php
    $direction = (string) ($event['direction'] ?? 'inbound');
    $voicemailState = (string) ($event['voicemail_state'] ?? 'none');
    $recordingState = (string) ($event['recording_state'] ?? 'none');
    $showRecording = ($event['title'] ?? '') !== 'Voicemail';
@endphp

<div @class(['d-flex', 'justify-content-end' => $direction === 'outbound'])>
    <article
        @class(['chat-bubble px-3 py-2', 'chat-bubble-me' => $direction === 'outbound'])
        style="max-width: 88%"
        data-call-session="{{ $event['call_session_id'] }}"
    >
        <div class="chat-bubble-title small text-secondary">
            <x-tabler.icon name="phone" />
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
        </div>

        <div class="chat-bubble-body">
            @if ($voicemailState === 'playable' && filled($event['voicemail_url'] ?? null))
                <div class="small fw-medium mt-1">Voicemail</div>
                <audio controls preload="none" class="mw-100" src="{{ $event['voicemail_url'] }}" aria-label="Voicemail" data-comms-call-audio></audio>
            @elseif ($voicemailState === 'unavailable')
                <div class="small text-secondary mt-1">Voicemail unavailable</div>
            @endif

            @if ($showRecording && $recordingState === 'playable' && filled($event['recording_url'] ?? null))
                <div class="small fw-medium mt-1">Recording</div>
                <audio controls preload="none" class="mw-100" src="{{ $event['recording_url'] }}" aria-label="Call recording" data-comms-call-audio></audio>
            @elseif ($showRecording && $recordingState === 'unavailable')
                <div class="small text-secondary mt-1">Recording unavailable</div>
            @endif

            @if (! empty($event['can_mark_handled']) && filled($event['mark_handled_url'] ?? null))
                <form method="POST" action="{{ $event['mark_handled_url'] }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="filter" value="{{ $listFilter ?? 'needs' }}">
                    <input type="hidden" name="owner" value="{{ $ownerFilter ?? 'everyone' }}">
                    @if (filled($thread['platform_conversation_public_id'] ?? null))
                        <input type="hidden" name="platform_conversation" value="{{ $thread['platform_conversation_public_id'] }}">
                    @endif
                    @if (filled($selected['conversation_id'] ?? null))
                        <input type="hidden" name="conversation" value="{{ $selected['conversation_id'] }}">
                    @endif
                    <button type="submit" class="btn btn-sm">
                        <x-tabler.icon name="check" />
                        Mark handled
                    </button>
                </form>
            @elseif (! empty($event['handled']))
                <span class="badge bg-green-lt mt-2">Handled</span>
            @endif
        </div>
    </article>
</div>
