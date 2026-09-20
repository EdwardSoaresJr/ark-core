@props([
    'callSessions',
    'timezone' => null,
])

@php
    $timezone = $timezone ?? \App\Ark\Operations\Settings\ShopDisplayTimezone::resolve();
    $recordingPlayback = app(\App\Ark\Operations\Telephony\CallRecordingPlayback::class);
@endphp

<div {{ $attributes->class(['divide-y divide-slate-100']) }}>
    @forelse ($callSessions as $callSession)
        <div class="px-3 py-2.5 text-xs leading-5 text-slate-700">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-black text-slate-950">
                        {{ $callSession->directionLabel() }}
                        @if ($callSession->repairOrder)
                            <span class="font-semibold text-slate-500">· RO #{{ $callSession->repairOrder->repair_order_id }}</span>
                        @endif
                    </p>
                    <p class="mt-0.5 text-slate-500">
                        {{ $callSession->started_at?->timezone($timezone)->format('M j, g:i A') ?? 'Unknown time' }}
                        · {{ $callSession->status->label() }}
                        @if ($callSession->owner)
                            · {{ $callSession->owner->name }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($recordingPlayback->urlFor($callSession, 'recording'))
                        <audio controls preload="none" class="h-8 max-w-[14rem]">
                            <source src="{{ $recordingPlayback->urlFor($callSession, 'recording') }}" type="audio/mpeg">
                        </audio>
                    @elseif ($callSession->hasRecording())
                        <span class="text-[11px] font-semibold text-slate-500">Recording on file</span>
                    @endif
                    @if ($recordingPlayback->urlFor($callSession, 'voicemail'))
                        <audio controls preload="none" class="h-8 max-w-[14rem]">
                            <source src="{{ $recordingPlayback->urlFor($callSession, 'voicemail') }}" type="audio/mpeg">
                        </audio>
                    @elseif ($callSession->hasVoicemail())
                        <span class="text-[11px] font-semibold text-slate-500">Voicemail on file</span>
                    @endif
                </div>
            </div>
            @if ($callSession->hasVoicemail() && ! $callSession->hasRecording())
                <p class="mt-1 text-[11px] font-semibold text-amber-800">Voicemail captured</p>
            @endif
        </div>
    @empty
        <div class="px-3 py-2 text-xs leading-4 text-slate-500">No calls recorded for this context yet.</div>
    @endforelse
</div>
