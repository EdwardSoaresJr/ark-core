@php
    $phoneVerificationRequired = $phoneVerificationRequired ?? false;
@endphp

<div @class(['public-panel public-panel--what-next', $class ?? ''])>
    <p class="text-sm font-semibold text-slate-900">What happens next</p>
    <ol class="mt-3 space-y-2.5 text-sm leading-relaxed text-slate-600">
        @if ($phoneVerificationRequired)
            <li class="flex gap-2.5">
                <span class="public-step-marker">1</span>
                Verify your phone and submit what&apos;s going on
            </li>
            <li class="flex gap-2.5">
                <span class="public-step-marker">2</span>
                You&apos;ll hear back with the next best step
            </li>
            <li class="flex gap-2.5">
                <span class="public-step-marker">3</span>
                Nothing is done without your approval
            </li>
        @else
            <li class="flex gap-2.5">
                <span class="public-step-marker">1</span>
                Tell us what&apos;s happening with your vehicle
            </li>
            <li class="flex gap-2.5">
                <span class="public-step-marker">2</span>
                You&apos;ll hear back with the next best step
            </li>
            <li class="flex gap-2.5">
                <span class="public-step-marker">3</span>
                Nothing is done without your approval
            </li>
        @endif
    </ol>
    <p class="mt-3 text-sm leading-relaxed text-slate-600">
        We&apos;ll talk through everything before any work is scheduled.
    </p>
</div>
