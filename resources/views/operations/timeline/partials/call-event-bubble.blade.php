@php
    use App\Ark\Operations\Telephony\CallSession;
    use App\Ark\Operations\Timeline\OperationalEventEntry;

    /** @var OperationalEventEntry $event */
    /** @var CallSession $callSession */
    $callSession = $event->subject;
    $timezone = config('app.display_timezone');
    $playback = $event->metadata;
    $outbound = $event->tone->value === 'shop';
    $duration = (int) ($callSession->dial_duration_seconds ?? 0);
    if ($duration === 0 && $callSession->answered_at !== null && $callSession->ended_at !== null) {
        $duration = max(0, $callSession->ended_at->diffInSeconds($callSession->answered_at, true));
    }
    $detailLines = array_values(array_filter([
        ($outbound ? 'Outgoing call' : 'Incoming call').' · '.$callSession->status->label(),
        $callSession->started_at ? 'Started '.$callSession->started_at->timezone($timezone)->format('M j, g:i A') : null,
        $callSession->answered_at ? 'Answered '.$callSession->answered_at->timezone($timezone)->format('g:i A') : null,
        $duration > 0 ? 'Length '.gmdate($duration >= 3600 ? 'G:i:s' : 'i:s', (int) $duration) : null,
        (int) ($callSession->voicemail_duration_seconds ?? 0) > 0 ? 'Voicemail '.gmdate('i:s', (int) $callSession->voicemail_duration_seconds) : null,
        (int) ($callSession->recording_duration_seconds ?? 0) > 0 ? 'Recording '.gmdate('i:s', (int) $callSession->recording_duration_seconds) : null,
        filled($event->actor) ? 'Handled by '.$event->actor : null,
    ]));
@endphp

<div @class(['d-flex', 'justify-content-end' => $outbound])>
    <article
        @class(['chat-bubble px-3 py-2', 'chat-bubble-me' => $outbound])
        style="max-width: 96%"
        data-comms-bubble="{{ $outbound ? 'outbound' : 'inbound' }}"
        data-call-session="{{ $callSession->getKey() }}"
    >
        <div class="chat-bubble-title small text-secondary">
            <x-tabler.icon name="phone" />
            {{ $event->headline }}
            @if (filled($event->metadata['visit_label'] ?? null))
                · {{ $event->metadata['visit_label'] }}
                @if (filled($event->metadata['visit_vehicle_label'] ?? null))
                    · {{ $event->metadata['visit_vehicle_label'] }}
                @endif
            @endif
            · Phone
            · {{ $event->occurredAt->timezone($timezone)->format('M j, g:i A') }}
        </div>
        <div class="chat-bubble-body">
            @if (filled($event->body))
                <p class="mb-0" style="white-space: pre-wrap">{{ $event->body }}</p>
            @endif
            @if (($playback['show_play_recording_action'] ?? false) && filled($playback['recording_url'] ?? null) && (int) ($callSession->recording_duration_seconds ?? 0) > 0)
                <div class="small fw-medium mt-1">Recording</div>
                <audio controls preload="none" class="mw-100" aria-label="Call recording" data-comms-call-audio>
                    <source src="{{ $playback['recording_url'] }}" type="audio/mpeg">
                </audio>
            @elseif ($callSession->hasRecording())
                <div class="small text-secondary mt-1" data-comms-call-media-note>Recording on file</div>
            @endif
            @if (($playback['show_play_voicemail_action'] ?? false) && filled($playback['voicemail_url'] ?? null) && (int) ($callSession->voicemail_duration_seconds ?? 0) > 0)
                <div class="small fw-medium mt-1">Voicemail</div>
                <audio controls preload="none" class="mw-100" aria-label="Voicemail" data-comms-call-audio>
                    <source src="{{ $playback['voicemail_url'] }}" type="audio/mpeg">
                </audio>
            @elseif ($callSession->hasVoicemail())
                <div class="small text-secondary mt-1" data-comms-call-media-note>Voicemail on file</div>
            @endif
            @if (filled($callSession->transcript))
                <details class="mt-1 small" data-comms-call-transcript @if ($callSession->hasVoicemail()) open @endif>
                    <summary class="text-secondary">{{ $callSession->hasVoicemail() ? 'Voicemail transcript' : 'Transcript' }}@if ((int) ($callSession->voicemail_duration_seconds ?? 0) > 0) · {{ gmdate('i:s', (int) $callSession->voicemail_duration_seconds) }}@endif</summary>
                    <p class="mb-0 mt-1" style="white-space: pre-wrap">{{ $callSession->transcript }}</p>
                </details>
            @endif
            <details class="mt-1 small" data-comms-details>
                <summary class="text-secondary">Details</summary>
                <ul class="list-unstyled mb-0 mt-1">
                    @foreach ($detailLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </details>
        </div>
    </article>
</div>
