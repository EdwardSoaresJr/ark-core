@php
    use App\Ark\Operations\Telephony\CallSession;
    use App\Ark\Operations\Timeline\OperationalEventEntry;

    /** @var OperationalEventEntry $event */
    /** @var CallSession $callSession */
    $callSession = $event->subject;
    $timezone = config('app.display_timezone');
    $playback = $event->metadata;
    $bubbleClass = match ($event->tone->value) {
        'shop' => 'ops-comms-workspace__bubble--outbound',
        default => 'ops-comms-workspace__bubble--inbound',
    };
@endphp

<article @class(['ops-comms-workspace__bubble', 'ops-comms-workspace__bubble--call', $bubbleClass])>
    <p class="ops-comms-workspace__bubble-meta">
        {{ $event->headline }}
        · Phone
        · {{ $event->occurredAt->timezone($timezone)->format('M j, g:i A') }}
    </p>
    @if (filled($event->body))
        <p class="ops-comms-workspace__bubble-body">{{ $event->body }}</p>
    @endif
    @if (($playback['show_play_recording_action'] ?? false) && filled($playback['recording_url'] ?? null))
        <div class="ops-comms-workspace__call-media">
            <p class="ops-comms-workspace__call-media-label">Recording</p>
            <audio controls preload="none" class="ops-comms-workspace__call-audio">
                <source src="{{ $playback['recording_url'] }}" type="audio/mpeg">
            </audio>
        </div>
    @elseif ($callSession->hasRecording())
        <p class="ops-comms-workspace__call-media-note">Recording on file</p>
    @endif
    @if (($playback['show_play_voicemail_action'] ?? false) && filled($playback['voicemail_url'] ?? null))
        <div class="ops-comms-workspace__call-media">
            <p class="ops-comms-workspace__call-media-label">Voicemail</p>
            <audio controls preload="none" class="ops-comms-workspace__call-audio">
                <source src="{{ $playback['voicemail_url'] }}" type="audio/mpeg">
            </audio>
        </div>
    @elseif ($callSession->hasVoicemail())
        <p class="ops-comms-workspace__call-media-note">Voicemail on file</p>
    @endif
</article>
