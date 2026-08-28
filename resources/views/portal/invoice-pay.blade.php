@php
    $shopName = \App\Ark\Operations\Settings\ShopSettings::current()->displayName();
    $payMode = $payMode ?? '';
    $isDeposit = in_array($payMode, ['deposit', 'remaining_deposit'], true)
        || ($amountLabel ?? '') === 'Deposit requested'
        || str_contains(strtolower((string) ($pageTitle ?? '')), 'deposit');
@endphp

<x-portal.app>
    <section
        class="customer-panel"
        @unless ($staffPreview ?? false)
            x-data="arkPortalInvoicePay({
                initiateUrl: @js(parse_url(route('portal.invoice-pay.attempts.store', ['token' => $token]), PHP_URL_PATH)),
                completeUrlTemplate: @js(parse_url(route('portal.invoice-pay.attempts.complete', ['token' => $token, 'attempt' => '__ATTEMPT__']), PHP_URL_PATH)),
                square: @js($square),
            })"
            x-init="boot()"
        @endunless
    >
        @if ($staffPreview ?? false)
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <p class="font-semibold">Staff preview</p>
                <p class="mt-1 text-amber-900">This is what the customer sees. Card entry is disabled here — use Send Pay Link from Comms to collect payment.</p>
            </div>
        @endif

        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $shopName }}</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ $pageTitle ?? 'Pay your invoice' }}</h1>

        <div class="mt-4 space-y-2 text-sm leading-6 text-slate-600">
            <p><span class="font-semibold text-slate-900">{{ $repairOrder->vehicle->display_name }}</span></p>
            <p>Repair order #{{ $repairOrder->repair_order_id }}</p>
            <p class="text-lg font-black text-slate-950">{{ $amountLabel ?? 'Balance due' }}: {{ $balanceDue }}</p>
            @if ($payMode === 'remaining_deposit')
                <p>
                    This is the leftover balance on work you already approved — not extra repairs.
                </p>
            @elseif ($isDeposit)
                <p>
                    This is a deposit toward approved work — not the full repair total unless those amounts match.
                    Paying does not approve any extra repairs.
                </p>
            @else
                <p>
                    This is the amount due on your invoice for work already approved or completed.
                </p>
            @endif
        </div>

        @unless ($staffPreview ?? false)
            <div class="mt-6 space-y-3">
                <div x-ref="cardMount" class="min-h-12 rounded-md border border-slate-200 bg-slate-50 p-3"></div>

                <button
                    type="button"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                    :disabled="busy || ! attempt"
                    @click="submitPayment()"
                >
                    <span x-show="! busy">{{ $payButtonLabel ?? 'Pay '.$balanceDue }}</span>
                    <span x-show="busy" x-cloak>Processing…</span>
                </button>

                <p x-show="message" x-text="message" x-cloak class="text-sm font-semibold text-emerald-800" aria-live="polite"></p>
                <p x-show="error" x-text="error" x-cloak class="text-sm font-semibold text-red-700" aria-live="polite"></p>
            </div>
        @endunless

        @include('portal.partials.vehicle-records-link', [
            'vehicleRecordsLink' => $vehicleRecordsLink ?? null,
            'vehicleName' => $repairOrder->vehicle->display_name,
        ])

        @include('portal.partials._shop-contact-card', [
            'shopPhone' => $shopPhone ?? null,
            'shopPhoneTel' => $shopPhoneTel ?? null,
            'class' => 'mt-4',
        ])
    </section>
</x-portal.app>
