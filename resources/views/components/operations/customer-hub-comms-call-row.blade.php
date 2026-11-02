@props([
    'callSession',
    'timezone' => null,
])

@php
    $timezone = $timezone ?? \App\Ark\Operations\Settings\ShopDisplayTimezone::resolve();
    $recordingPlayback = app(\App\Ark\Operations\Telephony\CallRecordingPlayback::class);
    $recordingUrl = $recordingPlayback->urlFor($callSession, 'recording');
    $voicemailUrl = $recordingPlayback->urlFor($callSession, 'voicemail');
@endphp

<div class="px-3 py-2 border-bottom">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <span class="badge bg-azure-lt">Call</span>
        <span class="text-secondary small">
            {{ $callSession->started_at?->timezone($timezone)->format('M j, g:i A') ?? 'Unknown time' }}
        </span>
    </div>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-bold">
                {{ $callSession->directionLabel() }}
                @if ($callSession->repairOrder)
                    <span class="fw-normal text-secondary">· RO #{{ $callSession->repairOrder->repair_order_id }}</span>
                @endif
            </div>
            <div class="text-secondary small">
                {{ $callSession->status->label() }}
                @if ($callSession->owner)
                    · {{ $callSession->owner->name }}
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($recordingUrl)
                <audio controls preload="none" class="mw-100" aria-label="Call recording">
                    <source src="{{ $recordingUrl }}" type="audio/mpeg">
                </audio>
            @elseif ($callSession->hasRecording())
                <span class="text-secondary small">Recording on file</span>
            @endif
            @if ($voicemailUrl)
                <audio controls preload="none" class="mw-100" aria-label="Voicemail">
                    <source src="{{ $voicemailUrl }}" type="audio/mpeg">
                </audio>
            @elseif ($callSession->hasVoicemail())
                <span class="text-secondary small">Voicemail on file</span>
            @endif
        </div>
    </div>
    @if ($callSession->hasVoicemail() && ! $callSession->hasRecording())
        <div class="mt-1 small text-warning">Voicemail captured</div>
    @endif
</div>
