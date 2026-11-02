@php
    /** @var \App\Ark\Operations\Telephony\TelephonyHealth $telephonyHealth */
    /** @var \App\Ark\Operations\Messaging\MessagingHealth $messagingHealth */
    /** @var \App\Ark\Operations\Messaging\Messenger\MessengerHealth $messengerHealth */
@endphp

<div class="d-flex flex-column gap-3">
    <div class="row g-2">
        <div class="col-12 col-md-6 col-xl-4">
            <div class="{{ $toneClasses[$telephonyHealth->providerTone($messagingHealth->webhookTone())] ?? $toneClasses['muted'] }} mb-0 py-2 small h-100">
                <div class="subheader">Phone</div>
                <div class="fw-bold">{{ $telephonyHealth->providerLabel() }}</div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="{{ $toneClasses[$telephonyHealth->voiceIngressTone()] ?? $toneClasses['muted'] }} mb-0 py-2 small h-100">
                <div class="subheader">Connection</div>
                <div class="fw-bold">{{ $telephonyHealth->voiceIngressLabel() }}</div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="{{ $toneClasses[$telephonyHealth->voiceSignalTone()] ?? $toneClasses['muted'] }} mb-0 py-2 small h-100">
                <div class="subheader">Voice webhook</div>
                <div class="fw-bold">{{ $telephonyHealth->voiceSignalLabel() }}</div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="{{ $toneClasses[$messagingHealth->webhookTone()] ?? $toneClasses['muted'] }} mb-0 py-2 small h-100">
                <div class="subheader">SMS / MMS webhook</div>
                <div class="fw-bold">{{ $messagingHealth->webhookLabel() }}</div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="{{ $toneClasses[$messengerHealth->webhookTone()] ?? $toneClasses['muted'] }} mb-0 py-2 small h-100">
                <div class="subheader">Messenger webhook</div>
                <div class="fw-bold">{{ $messengerHealth->webhookLabel() }}</div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="{{ $toneClasses[$telephonyHealth->reverbTone()] ?? $toneClasses['muted'] }} mb-0 py-2 small h-100">
                <div class="subheader">Screen pop</div>
                <div class="fw-bold">{{ $telephonyHealth->reverbLabel() }}</div>
            </div>
        </div>
    </div>

    <div class="row g-2">
        <div class="col-12 col-lg-6 col-xl-3">
            <div class="border rounded px-3 py-2 h-100">
                <div class="subheader">Last incoming call</div>
                @if ($lastCall)
                    <div class="fw-bold">
                        {{ $lastCall->customer?->name ?? $lastCall->from_number }}
                    </div>
                    <div class="small text-secondary">
                        {{ $telephonyHealth->formatRelative($lastCall->started_at) }}
                        @if ($telephonyHealth->formatTimestamp($lastCall->started_at))
                            · {{ $telephonyHealth->formatTimestamp($lastCall->started_at) }}
                        @endif
                    </div>
                @else
                    <div class="small fw-semibold text-secondary">No inbound calls recorded yet.</div>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-6 col-xl-3">
            <div class="border rounded px-3 py-2 h-100">
                <div class="subheader">Last voice webhook</div>
                @if ($lastVoiceWebhookAt)
                    <div class="fw-bold">{{ $telephonyHealth->formatRelative($lastVoiceWebhookAt) }}</div>
                    <div class="small text-secondary">{{ $telephonyHealth->formatTimestamp($lastVoiceWebhookAt) }}</div>
                @else
                    <div class="small fw-semibold text-secondary">Waiting for the first inbound call webhook.</div>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-6 col-xl-3">
            <div class="border rounded px-3 py-2 h-100">
                <div class="subheader">Last SMS / MMS webhook</div>
                @if ($lastSmsWebhookAt)
                    <div class="fw-bold">{{ $telephonyHealth->formatRelative($lastSmsWebhookAt) }}</div>
                    <div class="small text-secondary">{{ $telephonyHealth->formatTimestamp($lastSmsWebhookAt) }}</div>
                @else
                    <div class="small fw-semibold text-secondary">Waiting for the first inbound message webhook.</div>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-6 col-xl-3">
            <div class="border rounded px-3 py-2 h-100">
                <div class="subheader">Last Messenger webhook</div>
                @if ($lastMessengerWebhookAt)
                    <div class="fw-bold">{{ $messengerHealth->formatRelative($lastMessengerWebhookAt) }}</div>
                    <div class="small text-secondary">{{ $messengerHealth->formatTimestamp($lastMessengerWebhookAt) }}</div>
                @else
                    <div class="small fw-semibold text-secondary">Waiting for the first inbound Messenger webhook.</div>
                @endif
            </div>
        </div>
    </div>

    @if ($operationalNotes !== [])
        <div class="alert alert-warning small mb-0">
            <ul class="mb-0 ps-3">
                @foreach ($operationalNotes as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="small text-secondary">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between column-gap-3">
            <p class="fw-semibold text-body mb-0">Connected transports</p>
            <p class="small text-muted mb-0">{{ $telephonyHealth->credentialSourceLabel() }}</p>
        </div>
        <div class="border rounded px-3 py-2 mt-2">
            Stock Core does not publish carrier webhook URLs. SMS, voice, and Messenger stay unavailable until a transport implementation is installed.
        </div>
    </div>
</div>
