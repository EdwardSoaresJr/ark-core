@php
    $platformPaymentsCapture = (bool) ($platformPaymentsCapture ?? false);
@endphp

<section x-show="active === 'payments'" x-cloak class="space-y-3">
    <div class="border border-slate-300 bg-white p-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Square Payments</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Card capture inside ARK</h2>
            @if ($platformPaymentsCapture)
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Card capture for this shop is managed by ARK Platform. Merchant credentials, webhooks, and reader pairing are not stored or configured here.
                    Choose which shop surfaces may offer card capture. Take Payment and Record Payment stay on the repair order.
                </p>
            @else
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Connect ARK Payments to take cards in ARK. You can always record a card, cash, or check taken at the counter.
                    Merchant credentials and reader pairing are not configured in Core.
                    <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}" class="font-semibold text-slate-700 underline">Connect in ARK Platform</a>
                </p>
            @endif
        </div>

        @if ($platformPaymentsCapture)
            <div class="mt-3 rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900">
                Square capture is connected through ARK Platform.
            </div>
        @else
            <div class="mt-3 rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">
                Card capture is not connected. Record Payment still works on the repair order.
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.payments.update') }}" class="space-y-3 border border-slate-300 bg-white p-4">
        @csrf
        @method('PATCH')

        <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Capture surfaces</p>
        <p class="text-[11px] leading-4 text-slate-500">
            These are shop rules for where a card payment may be offered. They do not store a processor account.
        </p>

        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Preferred terminal</span>
            <input
                type="text"
                name="square_terminal_device_id"
                value="{{ old('square_terminal_device_id', $settings->square_terminal_device_id) }}"
                class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                placeholder="Default counter reader"
                autocomplete="off"
            >
            <span class="mt-1 block text-[11px] leading-4 text-slate-500">Used for counter Take Payment when this shop has a reader. Leave blank if none is assigned yet.</span>
        </label>

        <div class="grid gap-2 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="square_terminal_enabled" value="0">
                <input type="checkbox" name="square_terminal_enabled" value="1" @checked(old('square_terminal_enabled', $settings->square_terminal_enabled))>
                Counter terminal
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="square_keyed_enabled" value="0">
                <input type="checkbox" name="square_keyed_enabled" value="1" @checked(old('square_keyed_enabled', $settings->square_keyed_enabled))>
                Counter keyed entry
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="square_portal_pay_enabled" value="0">
                <input type="checkbox" name="square_portal_pay_enabled" value="1" @checked(old('square_portal_pay_enabled', $settings->square_portal_pay_enabled))>
                Customer portal pay
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="square_email_pay_enabled" value="0">
                <input type="checkbox" name="square_email_pay_enabled" value="1" @checked(old('square_email_pay_enabled', $settings->square_email_pay_enabled))>
                Emailed invoice pay link
            </label>
        </div>

        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
            Save payment surfaces
        </button>
    </form>
</section>
