@php
    $outbound = app(\App\Ark\Mail\OutboundTransactionalMail::class);
    $statusLabel = $outbound->statusLabel();
    $mailReady = $outbound->isReady();
@endphp

<div class="space-y-4">
    <div>
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Customer email</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Estimates, invoices, and documents send through ARK Email when this shop is connected.
            Reply-to stays on this shop so customer replies reach you.
        </p>
        <p class="mt-2 text-xs font-semibold text-slate-800">Status: {{ $statusLabel }}</p>
        @unless ($mailReady)
            <p class="mt-2 rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-950">
                Outbound customer email is not connected. You can still set reply-to. Messages will not send until ARK Email is connected.
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}" class="font-semibold underline">Connect in ARK Platform</a>
            </p>
        @endunless
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.email.update') }}" class="space-y-4 rounded-sm border border-slate-200 bg-white p-3">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Reply-To</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Customer replies go here. Defaults to shop email when blank.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Reply-to address</span>
                <input
                    type="email"
                    name="postmark_reply_to"
                    value="{{ old('postmark_reply_to', $settings->postmark_reply_to ?: ($settings->email ?: config('mail.reply_to.address'))) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                    placeholder="hello@yourshop.com"
                >
            </label>
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Reply-to name</span>
                <input
                    type="text"
                    name="postmark_reply_to_name"
                    value="{{ old('postmark_reply_to_name', $settings->postmark_reply_to_name ?: config('mail.reply_to.name')) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                    placeholder="{{ $settings->shop_name ?: 'Shop name' }}"
                >
            </label>
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                Save reply-to settings
            </button>
        </div>
    </form>
</div>
