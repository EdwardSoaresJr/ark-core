@props([
    'depositEnabled' => false,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-[rgb(var(--public-link-rgb)_/_0.3)] bg-[rgb(var(--public-link-rgb)_/_0.05)] px-4 py-4 sm:px-5 '.$class]) }}>
    <p class="text-sm font-semibold text-slate-950">How to approve your estimate</p>
    <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm leading-6 text-slate-700">
        <li>
            For each repair, choose
            <span class="font-semibold text-slate-900">Approve</span> (do it now),
            <span class="font-semibold text-slate-900">Defer</span> (not now - keep it for later),
            or <span class="font-semibold text-slate-900">Decline</span> (do not do this work).
        </li>
        <li>Tap the button at the bottom to send your choices.</li>
        @if ($depositEnabled)
            <li>If a deposit is required, pay it on the next step so we can schedule the work you approved.</li>
        @endif
    </ol>
</div>
