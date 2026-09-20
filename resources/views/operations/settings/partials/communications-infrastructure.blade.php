@php
    /** @var \App\Ark\Operations\Telephony\TelephonyHealth $telephonyHealth */

    $toneClasses = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900',
        'muted' => 'border-slate-200 bg-slate-50 text-slate-700',
    ];
    $platformVoiceManaged = (bool) ($platformVoiceManaged ?? \App\Ark\Platform\Voice\ManagedVoiceGate::platformVoiceReady());
@endphp

@if (auth()->user()?->isMasterAdmin())
    <div class="border border-slate-300 bg-white px-4 py-4">
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Communications infrastructure</p>
        @if ($platformVoiceManaged)
            <h3 class="mt-1 text-sm font-black text-slate-950">Hosted Voice (ARK Cloud)</h3>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-600">
                Inbound Voice for this installation is executed on ARK Platform. Core receives CallSession and media metadata over Connect/Fabric —
                Core Twilio Voice webhook URLs below are legacy and must not be the Twilio Voice URL for the Hosted shop number.
            </p>
            <div class="mt-4 space-y-2 text-xs leading-5 text-slate-600">
                <p>
                    <span class="font-semibold text-slate-900">Fabric ingress</span>
                    <span class="mt-0.5 block font-mono text-[11px] leading-4 text-slate-500">{{ url('/webhooks/cloud/fabric/events') }}</span>
                </p>
                <p>
                    <span class="font-semibold text-slate-900">Phone settings</span>
                    <a href="https://cloud.arksms.com" class="mt-0.5 block font-semibold underline" target="_blank" rel="noopener">cloud.arksms.com</a>
                </p>
                <details class="mt-2">
                    <summary class="cursor-pointer font-semibold text-slate-900">Legacy Core Voice URLs (do not point Twilio here for Hosted)</summary>
                    <p class="mt-2 font-mono text-[11px] leading-4 text-slate-500">{{ $telephonyHealth->webhookUrl() }}</p>
                    <p class="mt-1 font-mono text-[11px] leading-4 text-slate-500">{{ $telephonyHealth->sipOutboundWebhookUrl() }}</p>
                </details>
            </div>
        @else
            <h3 class="mt-1 text-sm font-black text-slate-950">Twilio voice transport</h3>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-600">
                Voice runs through Twilio webhooks and SIP endpoints. Shop devices, coverage, and operators live under Shop → Communications.
            </p>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$telephonyHealth->connectionTone()] ?? $toneClasses['muted'] }}">
                    <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Connection</p>
                    <p class="mt-1 text-sm font-black">{{ $telephonyHealth->connectionLabel() }}</p>
                </div>
                <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$telephonyHealth->webhookTone()] ?? $toneClasses['muted'] }}">
                    <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Voice webhook</p>
                    <p class="mt-1 text-sm font-black">{{ $telephonyHealth->webhookLabel() }}</p>
                </div>
            </div>

            <div class="mt-4 space-y-2 text-xs leading-5 text-slate-600">
                <p>
                    <span class="font-semibold text-slate-900">Voice inbound</span>
                    <span class="mt-0.5 block font-mono text-[11px] leading-4 text-slate-500">{{ $telephonyHealth->webhookUrl() }}</span>
                </p>
                <p>
                    <span class="font-semibold text-slate-900">SIP outbound</span>
                    <span class="mt-0.5 block font-mono text-[11px] leading-4 text-slate-500">{{ $telephonyHealth->sipOutboundWebhookUrl() }}</span>
                </p>
                <p class="text-slate-500">{{ $telephonyHealth->credentialSourceLabel() }}</p>
            </div>
        @endif
    </div>
@endif
