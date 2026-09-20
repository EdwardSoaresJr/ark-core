@props([
    'token',
    'portalAuthorization',
    'square',
    'staffPreview' => false,
    'payingRemaining' => false,
    'class' => '',
])

@php
    $depositAmountLabel = $portalAuthorization['deposit_amount'] ?? $portalAuthorization['approved_amount'];
@endphp

<section
    id="portal-estimate-deposit"
    @class(['scroll-mt-28 overflow-hidden rounded-xl border-2 border-amber-400 bg-white shadow-sm space-y-0', $class])
    @unless ($staffPreview)
    x-data="arkPortalEstimateDeposit({
        initiateUrl: @js(parse_url(route('portal.estimates.deposits.store', ['token' => $token]), PHP_URL_PATH)),
        completeUrlTemplate: @js(parse_url(route('portal.estimates.deposits.complete', ['token' => $token, 'attempt' => '__ATTEMPT__']), PHP_URL_PATH)),
        approvalId: @js($portalAuthorization['approval_id']),
        square: @js($square),
    })"
    x-init="boot()"
    @endunless
>
    <div class="border-b border-amber-200 bg-amber-50 px-4 py-4 sm:px-5">
        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-amber-800">{{ $payingRemaining ? 'Pay remaining balance' : 'Step 3 — Pay deposit' }}</p>
        <h2 class="mt-1 text-lg font-bold text-slate-950">Pay {{ $depositAmountLabel }} {{ $payingRemaining ? 'remaining' : 'deposit' }}</h2>
        <p class="mt-1 text-sm leading-6 text-slate-700">
            @if ($payingRemaining)
                This is the leftover balance on work you already approved. It is not approval for any additional repairs.
            @else
                This deposit is for the work you already approved. We use it to schedule that work.
                It is not approval for any additional repairs.
            @endif
        </p>
    </div>

    <div class="space-y-4 px-4 py-4 sm:px-5">
    @if ($staffPreview)
        <p class="rounded-md border border-amber-200 bg-amber-50/80 px-3 py-3 text-sm leading-6 text-amber-950">
            Card deposit is disabled in staff preview — open the shared customer estimate link to collect the deposit.
        </p>
    @else
    <div x-ref="cardMount" class="min-h-12 rounded-md border border-slate-200 bg-slate-50 p-3"></div>

    <button
        type="button"
        class="inline-flex min-h-12 w-full items-center justify-center rounded-md bg-amber-600 px-4 text-base font-semibold text-white shadow-sm hover:bg-amber-700 disabled:opacity-60 sm:text-sm"
        :disabled="busy || ! attempt"
        @click="submitPayment()"
    >
        <span x-show="! busy">Pay {{ $payingRemaining ? 'remaining' : 'deposit' }} {{ $depositAmountLabel }}</span>
        <span x-show="busy" x-cloak>Processing…</span>
    </button>

    <p x-show="message" x-text="message" x-cloak class="text-sm font-semibold text-emerald-800" aria-live="polite"></p>
    <p x-show="error" x-text="error" x-cloak class="text-sm font-semibold text-red-700" aria-live="polite"></p>
    @endif
    </div>
</section>
