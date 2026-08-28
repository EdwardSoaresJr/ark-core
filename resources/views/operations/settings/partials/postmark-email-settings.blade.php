@php
    $integrations = $shopIntegrations ?? \App\Ark\Operations\Settings\ShopIntegrationCredentials::forCurrentShop();
    $postmarkReady = $integrations->postmarkConfigured();
@endphp

<form method="POST" action="{{ route('operations.settings.shop.email.update') }}" class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Postmark email</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Outbound estimate, invoice, and operational email uses Postmark when <code class="text-[11px]">MAIL_MAILER=postmark</code>.
                Secrets are encrypted in the shop database. Leave token blank to keep the current value.
                {{ $integrations->credentialSourceLabel($integrations->postmarkCredentialSource()) }}
            </p>
        </div>

        <div class="rounded-sm border px-3 py-2 text-xs font-semibold {{ $postmarkReady ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
            @if ($postmarkReady)
                Postmark server token detected.
            @else
                Postmark is not configured. Estimate and invoice email will fail until a server token is saved.
            @endif
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Server token</span>
                <input
                    type="password"
                    name="postmark_token"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="{{ $integrations->hasStoredPostmarkToken() ? 'Saved — leave blank to keep' : 'Postmark server API token' }}"
                    autocomplete="new-password"
                >
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Reply-to address</span>
                <input
                    type="email"
                    name="postmark_reply_to"
                    value="{{ old('postmark_reply_to', $settings->postmark_reply_to ?: config('mail.reply_to.address')) }}"
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

            <label class="block sm:col-span-2">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Message stream ID</span>
                <input
                    type="text"
                    name="postmark_message_stream_id"
                    value="{{ old('postmark_message_stream_id', $settings->postmark_message_stream_id) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="Optional — outbound stream"
                    autocomplete="off"
                >
            </label>
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                Save email settings
            </button>
        </div>
    </form>
