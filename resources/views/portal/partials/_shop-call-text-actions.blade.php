@props([
    'phoneTel' => '',
    'class' => '',
])

@php
    $digits = preg_replace('/\D+/', '', (string) $phoneTel);
    $telHref = filled($digits) ? 'tel:'.$digits : null;
    $smsHref = filled($digits) ? 'sms:'.$digits : null;
@endphp

@if ($telHref && $smsHref)
    <div @class(['grid grid-cols-2 gap-3', $class])>
        <a
            href="{{ $telHref }}"
            class="inline-flex min-h-11 items-center justify-center rounded-[var(--public-radius-control)] bg-[color:var(--public-brand)] px-4 text-sm font-semibold text-[color:var(--public-on-brand)] shadow-sm hover:bg-[color:var(--public-brand-hover)]"
        >
            Call
        </a>
        <a
            href="{{ $smsHref }}"
            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-900 hover:bg-slate-50"
        >
            Text
        </a>
    </div>
@endif
