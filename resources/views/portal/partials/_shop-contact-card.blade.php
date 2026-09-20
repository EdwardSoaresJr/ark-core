@props([
    'shopPhone' => null,
    'shopPhoneTel' => null,
    'heading' => 'Questions?',
    'body' => null,
    'variant' => 'default',
    'class' => '',
])

@php
    $digits = preg_replace('/\D+/', '', (string) ($shopPhoneTel ?? $shopPhone ?? ''));
    $bodyText = $body ?? (filled($shopPhone) ? 'Call or text us at '.$shopPhone.'.' : 'Call or text us.');
    $wrapperClass = match ($variant) {
        'embedded' => 'border-t border-slate-100 pt-4',
        default => 'rounded-xl border border-slate-200 bg-white px-4 py-3.5',
    };
@endphp

@if (filled($digits))
    <div @class([$wrapperClass, 'text-sm text-slate-700', $class])>
        <div>
            <p class="font-semibold text-slate-950">{{ $heading }}</p>
            <p class="mt-1 leading-6">{{ $bodyText }}</p>
        </div>
        @include('portal.partials._shop-call-text-actions', [
            'phoneTel' => $digits,
            'class' => 'mt-3',
        ])
    </div>
@else
    <p @class(['text-xs leading-5 text-slate-500', $class])>{{ $heading }} Call or text us.</p>
@endif
