@php
    $outbound = app(\App\Ark\Mail\OutboundTransactionalMail::class);
    $cloud = \App\Ark\Cloud\CloudConnection::current();
    $cloud->clearExpiredPairing();
    $statusLabel = $outbound->statusLabel();
    $arkConnected = $cloud->isConnected();
    $arkPairing = $cloud->isPairing();
    $pairingCode = $cloud->pairingCode();
    $pairingPublicId = $cloud->pairingPublicId();
@endphp

<div class="space-y-4">
    <div>
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Customer Email</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Transactional customer email (estimates, invoices, documents) is delivered through ARK Mail.
            Marketing and broadcast are not supported.
        </p>
    </div>

    <div class="space-y-3 rounded-sm border border-slate-200 bg-white p-3">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-900">ARK Mail</p>
                <p class="mt-0.5 text-xs leading-5 text-slate-500">
                    Managed transactional email for ARK. Connect this Box to ARK Cloud to send customer email.
                </p>
                <p class="mt-1 text-xs text-slate-500">Status: {{ $statusLabel }}</p>
            </div>
            <span class="shrink-0 rounded-sm border px-2 py-1 text-[10px] font-bold uppercase tracking-wide
                {{ $arkConnected ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : ($cloud->isSuspended() ? 'border-rose-200 bg-rose-50 text-rose-900' : ($arkPairing ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-slate-200 bg-slate-50 text-slate-600')) }}">
                {{ $arkPairing ? 'Pairing' : ($arkConnected ? 'Connected' : 'Not connected') }}
            </span>
        </div>

        @if (! $arkConnected && ! $arkPairing)
            <p class="rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-950">
                Outbound customer email is not configured. Connect ARK Mail to send estimates, invoices, and documents.
            </p>
        @endif

        @if ($arkConnected)
            <dl class="grid gap-1 text-xs text-slate-600">
                <div><span class="font-semibold text-slate-400">From</span> {{ $settings->ark_mail_from_email ?: 'Managed by ARK Mail' }}</div>
                <div><span class="font-semibold text-slate-400">Reply-To</span> {{ $settings->postmark_reply_to ?: $settings->email }}</div>
            </dl>
            <form method="POST" action="{{ route('operations.settings.shop.email.ark-mail.disconnect') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                    Disconnect ARK Mail
                </button>
            </form>
        @elseif ($arkPairing && $pairingPublicId)
            <div class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                @if ($pairingCode)
                    <p class="font-semibold text-slate-900">Pairing code: <span class="font-mono tracking-widest">{{ $pairingCode }}</span></p>
                @endif
                <p class="mt-1 text-slate-500">Approve this code in ARK Cloud for the correct shop, then finish connecting.</p>
            </div>
            <form method="POST" action="{{ route('operations.settings.shop.email.ark-mail.claim') }}">
                @csrf
                <input type="hidden" name="pairing_public_id" value="{{ $pairingPublicId }}">
                <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                    Finish connecting
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('operations.settings.shop.email.ark-mail.enable') }}" class="space-y-2">
                @csrf
                @if (! config('services.ark_cloud.base_url') && ! config('services.ark_mail.base_url'))
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Cloud URL (dev)</span>
                        <input
                            type="url"
                            name="ark_mail_service_url"
                            value="{{ old('ark_mail_service_url', $settings->cloud_base_url ?: $settings->ark_mail_service_url) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                            placeholder="http://ark-cloud.test"
                        >
                    </label>
                @endif
                <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                    Connect ARK Mail
                </button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.email.update') }}" class="space-y-4 rounded-sm border border-slate-200 bg-white p-3">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Reply-To</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Customer replies go here. Defaults to Shop Profile email when blank.
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
