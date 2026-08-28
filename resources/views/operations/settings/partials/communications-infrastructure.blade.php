@php
    /** @var \App\Ark\Operations\Telephony\TelephonyHealth $telephonyHealth */

    $toneClasses = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900',
        'muted' => 'border-slate-200 bg-slate-50 text-slate-700',
    ];
@endphp

@if (auth()->user()?->isMasterAdmin())
    <div class="border border-slate-300 bg-white px-4 py-4">
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Communications infrastructure</p>
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
    </div>
@endif
