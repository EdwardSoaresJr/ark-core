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
        <h3 class="mt-1 text-sm font-black text-slate-950">Messaging and voice transport</h3>
        <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-600">
            Stock ARK Core does not include a configured messaging or voice transport. Conversations and call sessions remain available as shop management surfaces. Outbound SMS and live voice require a transport implementation.
        </p>

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <div class="rounded-sm border px-3 py-2 {{ $toneClasses['muted'] }}">
                <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Messaging</p>
                <p class="mt-1 text-sm font-black">Not configured</p>
            </div>
            <div class="rounded-sm border px-3 py-2 {{ $toneClasses['muted'] }}">
                <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Voice</p>
                <p class="mt-1 text-sm font-black">Not configured</p>
            </div>
        </div>
    </div>
@endif
