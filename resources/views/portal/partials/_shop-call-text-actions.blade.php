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
            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#0099cc] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#007aa3]"
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
