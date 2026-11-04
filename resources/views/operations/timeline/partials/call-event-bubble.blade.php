@php
    use App\Ark\Operations\Telephony\CallSession;
    use App\Ark\Operations\Timeline\OperationalEventEntry;

    /** @var OperationalEventEntry $event */
    /** @var CallSession $callSession */
    $callSession = $event->subject;
    $timezone = config('app.display_timezone');
    $playback = $event->metadata;
    $outbound = $event->tone->value === 'shop';
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
        </div>
    </article>
</div>
