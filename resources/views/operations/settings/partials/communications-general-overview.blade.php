@php
    /** @var \App\Ark\Operations\Telephony\TelephonyHealth $telephonyHealth */
    /** @var \App\Ark\Operations\Messaging\MessagingHealth $messagingHealth */
    /** @var \App\Ark\Operations\Messaging\Messenger\MessengerHealth $messengerHealth */
@endphp

<div class="space-y-3">
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$telephonyHealth->providerTone($messagingHealth->webhookTone())] ?? $toneClasses['muted'] }}">
            <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Phone</p>
            <p class="mt-1 text-sm font-black">{{ $telephonyHealth->providerLabel() }}</p>
        </div>

        <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$telephonyHealth->voiceIngressTone()] ?? $toneClasses['muted'] }}">
            <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Connection</p>
            <p class="mt-1 text-sm font-black">{{ $telephonyHealth->voiceIngressLabel() }}</p>
        </div>

        <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$telephonyHealth->voiceSignalTone()] ?? $toneClasses['muted'] }}">
            <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Voice webhook</p>
            <p class="mt-1 text-sm font-black">{{ $telephonyHealth->voiceSignalLabel() }}</p>
        </div>

        <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$messagingHealth->webhookTone()] ?? $toneClasses['muted'] }}">
            <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">SMS / MMS webhook</p>
            <p class="mt-1 text-sm font-black">{{ $messagingHealth->webhookLabel() }}</p>
        </div>

        <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$messengerHealth->webhookTone()] ?? $toneClasses['muted'] }}">
            <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Messenger webhook</p>
            <p class="mt-1 text-sm font-black">{{ $messengerHealth->webhookLabel() }}</p>
        </div>

        <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$telephonyHealth->reverbTone()] ?? $toneClasses['muted'] }}">
            <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Screen pop</p>
            <p class="mt-1 text-sm font-black">{{ $telephonyHealth->reverbLabel() }}</p>
        </div>
    </div>

    <div class="grid gap-2 lg:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-sm border border-slate-200 bg-slate-50/60 px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Last incoming call</p>
            @if ($lastCall)
                <p class="mt-1 text-sm font-black text-slate-950">
                    {{ $lastCall->customer?->name ?? $lastCall->from_number }}
                </p>
                <p class="mt-0.5 text-xs text-slate-600">
                    {{ $telephonyHealth->formatRelative($lastCall->started_at) }}
                    @if ($telephonyHealth->formatTimestamp($lastCall->started_at))
                        · {{ $telephonyHealth->formatTimestamp($lastCall->started_at) }}
                    @endif
                </p>
            @else
                <p class="mt-1 text-sm font-semibold text-slate-600">No inbound calls recorded yet.</p>
            @endif
        </div>

        <div class="rounded-sm border border-slate-200 bg-slate-50/60 px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Last voice webhook</p>
            @if ($lastVoiceWebhookAt)
                <p class="mt-1 text-sm font-black text-slate-950">{{ $telephonyHealth->formatRelative($lastVoiceWebhookAt) }}</p>
                <p class="mt-0.5 text-xs text-slate-600">{{ $telephonyHealth->formatTimestamp($lastVoiceWebhookAt) }}</p>
            @else
                <p class="mt-1 text-sm font-semibold text-slate-600">Waiting for the first inbound call webhook.</p>
            @endif
        </div>

        <div class="rounded-sm border border-slate-200 bg-slate-50/60 px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Last SMS / MMS webhook</p>
            @if ($lastSmsWebhookAt)
                <p class="mt-1 text-sm font-black text-slate-950">{{ $telephonyHealth->formatRelative($lastSmsWebhookAt) }}</p>
                <p class="mt-0.5 text-xs text-slate-600">{{ $telephonyHealth->formatTimestamp($lastSmsWebhookAt) }}</p>
            @else
                <p class="mt-1 text-sm font-semibold text-slate-600">Waiting for the first inbound message webhook.</p>
            @endif
        </div>

        <div class="rounded-sm border border-slate-200 bg-slate-50/60 px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Last Messenger webhook</p>
            @if ($lastMessengerWebhookAt)
                <p class="mt-1 text-sm font-black text-slate-950">{{ $messengerHealth->formatRelative($lastMessengerWebhookAt) }}</p>
                <p class="mt-0.5 text-xs text-slate-600">{{ $messengerHealth->formatTimestamp($lastMessengerWebhookAt) }}</p>
            @else
                <p class="mt-1 text-sm font-semibold text-slate-600">Waiting for the first inbound Messenger webhook.</p>
            @endif
        </div>
    </div>

    @if ($operationalNotes !== [])
        <div class="rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-950">
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($operationalNotes as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="text-xs text-slate-600">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
            <p class="font-semibold text-slate-800">Webhook URLs</p>
            <p class="text-[10px] text-slate-400">{{ $telephonyHealth->credentialSourceLabel() }} · WebSocket host from {{ $telephonyHealth->reverbHostSourceLabel() }}</p>
        </div>
        <div
            class="mt-1.5 divide-y divide-slate-200 rounded-sm border border-slate-200 bg-white"
            x-data="{
                copiedLabel: null,
                async copyUrl(url, label) {
                    try {
                        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                            await navigator.clipboard.writeText(url);
                        } else {
                            window.prompt('Copy URL:', url);
                            return;
                        }
                        this.copiedLabel = label;
                        window.setTimeout(() => { this.copiedLabel = null; }, 2000);
                    } catch (error) {
                        window.prompt('Copy URL:', url);
                    }
                },
            }"
        >
            @php
                $voiceWebhookRows = [[
                        'label' => 'Voice inbound',
                        'hint' => 'Phone → A call comes in',
                        'url' => $telephonyHealth->webhookUrl(),
                    ], [
                        'label' => 'SIP outbound',
                        'hint' => 'SIP domain → Call Control',
                        'url' => $telephonyHealth->sipOutboundWebhookUrl(),
                    ]];
            @endphp
            @foreach (array_merge($voiceWebhookRows, [
                [
                    'label' => 'SMS / MMS',
                    'hint' => 'Phone → Messaging',
                    'url' => $messagingHealth->webhookUrl(),
                ],
                [
                    'label' => 'Messenger',
                    'hint' => 'Meta Page → Messaging',
                    'url' => $messengerHealth->webhookUrl(),
                ],
            ]) as $webhook)
                <button
                    type="button"
                    @click="copyUrl(@js($webhook['url']), @js($webhook['label']))"
                    class="flex w-full items-center gap-2 px-2 py-1.5 text-left transition hover:bg-slate-50"
                    title="{{ $webhook['hint'] }} — click to copy"
                >
                    <span class="w-20 shrink-0 text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $webhook['label'] }}</span>
                    <span class="min-w-0 flex-1 truncate font-mono text-[11px] text-slate-700">{{ $webhook['url'] }}</span>
                    <span
                        class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-slate-400"
                        x-show="copiedLabel !== @js($webhook['label'])"
                    >Copy</span>
                    <span
                        class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-emerald-700"
                        x-show="copiedLabel === @js($webhook['label'])"
                        x-cloak
                    >Copied</span>
                </button>
            @endforeach
        </div>
    </div>
</div>
