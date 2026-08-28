@props([
    'shopPhone' => null,
    'showDeposit' => false,
    'depositAmount' => null,
    'payingRemaining' => false,
])

<div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
    <p class="font-semibold text-slate-950">What happens next</p>
    <ul class="mt-2 list-disc space-y-1 pl-5 leading-6">
        @if ($showDeposit && filled($depositAmount))
            <li>
                @if ($payingRemaining)
                    <span class="font-semibold text-slate-950">Pay the remaining {{ $depositAmount }} below</span>
                    whenever you are ready.
                @else
                    <span class="font-semibold text-slate-950">Pay your {{ $depositAmount }} deposit below</span>
                    so we can schedule the work you approved.
                @endif
            </li>
        @endif
        <li>Your advisor reviews the services you approved.</li>
        <li>We’ll text you about scheduling, or if we need anything else.</li>
    </ul>

    @include('portal.partials._shop-contact-card', [
        'shopPhone' => $shopPhone,
        'heading' => 'Questions?',
        'body' => filled($shopPhone) ? 'Call or text us at '.$shopPhone.'.' : 'Call or text us.',
        'variant' => 'embedded',
        'class' => 'mt-4',
    ])
</div>
