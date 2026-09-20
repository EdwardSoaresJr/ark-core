@props([
    'depositAmount',
    'payingRemaining' => false,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-4 sm:px-5 '.$class]) }}>
    @if ($payingRemaining)
        <p class="text-base font-bold text-amber-950">Next step: pay the remaining balance</p>
        <p class="mt-1 text-sm leading-6 text-amber-900">
            We’ve got your approval.
        </p>
        <p class="mt-1 text-sm leading-6 text-amber-900">
            A {{ $depositAmount }} remaining balance is needed before we can finish scheduling.
            Paying now does not approve any extra repairs.
        </p>
        <a
            href="#portal-estimate-deposit"
            class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-amber-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 sm:w-auto"
        >
            Pay {{ $depositAmount }} remaining
        </a>
    @else
        <p class="text-base font-bold text-amber-950">Next step: pay your deposit</p>
        <p class="mt-1 text-sm leading-6 text-amber-900">
            We’ve got your approval.
        </p>
        <p class="mt-1 text-sm leading-6 text-amber-900">
            A {{ $depositAmount }} deposit is needed before we can schedule the approved work.
            Paying the deposit does not approve any extra repairs.
        </p>
        <a
            href="#portal-estimate-deposit"
            class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-amber-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 sm:w-auto"
        >
            Pay {{ $depositAmount }} deposit
        </a>
    @endif
</div>
