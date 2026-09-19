@props([
    'shopPhone' => null,
    'showDeposit' => false,
    'depositAmount' => null,
    'payingRemaining' => false,
])

<div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
    <p class="font-semibold text-slate-950">What happens next</p>
    <div class="mt-2 space-y-2 leading-6">
        @if ($showDeposit && filled($depositAmount))
            <p>
                @if ($payingRemaining)
                    A {{ $depositAmount }} remaining balance is needed before we can finish scheduling.
                @else
                    A {{ $depositAmount }} deposit is needed before we can schedule the approved work.
                @endif
            </p>
        @endif
        <p>We’ll review your approval and get everything moving. We’ll text you with updates or if we need anything else.</p>
    </div>

    @include('portal.partials._shop-contact-card', [
        'shopPhone' => $shopPhone,
        'heading' => 'Questions?',
        'body' => filled($shopPhone) ? 'Call or text us at '.$shopPhone.'.' : 'Call or text us.',
        'variant' => 'embedded',
        'class' => 'mt-4',
    ])
</div>
