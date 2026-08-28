@php
    use App\Ark\Operations\Messaging\Messenger\MessengerChannelConnection;
    use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;

    $channelConnection = MessengerChannelConnection::forCurrentShop();
    $platform = $channelConnection->platform();
    $shopConnection = $channelConnection->shopConnection();
    $messengerHealth = $channelConnection->health();
    $messengerArkademyUrl = \App\Ark\Operations\Learn\ArkademyUrls::pageUrlOrHome('admin', 'messenger-setup');
    $statusTone = $channelConnection->statusTone();
    $webhookToneClasses = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900',
        'muted' => 'border-slate-200 bg-slate-50 text-slate-700',
    ];
@endphp

<form
    method="POST"
    action="{{ route('operations.settings.shop.telephony.update') }}"
    class="space-y-3"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="communications_tab" value="messenger">

    <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
        <div class="border-b border-slate-200 pb-3">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Facebook Messenger</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Connect this shop’s Facebook Page. The Meta App and webhook belong to ARK.
                <x-operations.learn.guide-link role="admin" article="messenger-setup" :label="\App\Support\Branding\Branding::learnName().' → Messenger setup'" class="font-semibold text-slate-700 decoration-slate-300 hover:text-slate-950" />
            </p>
        </div>

        <div class="rounded-sm border px-3 py-2.5 {{ $webhookToneClasses[$statusTone] ?? $webhookToneClasses['muted'] }}">
            <p class="text-xs font-bold uppercase tracking-wide">Messenger</p>
            <p class="mt-1 text-sm font-semibold">{{ $channelConnection->statusLabel() }}</p>
            <dl class="mt-2 grid gap-1 text-[11px] font-medium sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Page</dt>
                    <dd class="font-semibold text-slate-900">
                        {{ $shopConnection->pageName() ?: '—' }}
                        @if ($shopConnection->maskedPageId())
                            <span class="font-mono text-slate-600">· {{ $shopConnection->maskedPageId() }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Last webhook</dt>
                    <dd class="font-semibold text-slate-900">
                        {{ $messengerHealth->formatRelative($messengerHealth->lastWebhookAt()) ?: 'Never' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Platform</dt>
                    <dd class="font-semibold text-slate-900">
                        {{ $platform->isConfigured() ? 'Configured' : 'Missing App Secret / Verify Token' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Page connection</dt>
                    <dd class="font-semibold text-slate-900">
                        {{ $shopConnection->isConfigured() ? 'Configured' : 'Incomplete' }}
                    </dd>
                </div>
            </dl>
            @if ($platform->webhookUrl() !== '')
                <p class="mt-2 text-[11px] font-medium text-slate-700">
                    ARK webhook callback:
                    <span class="font-mono text-slate-900">{{ $platform->webhookUrl() }}</span>
                </p>
            @endif
        </div>

        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <input
                type="checkbox"
                name="channels[messenger][enabled]"
                value="1"
                class="rounded border-slate-300 text-slate-900"
                @checked(old('channels.messenger.enabled', $shopConnection->isEnabled()))
            >
            Enable Messenger ingress and queue
            <span class="text-[11px] font-normal text-slate-500">— turn on after the Page is connected</span>
        </label>

        <details class="rounded-sm border border-slate-200 bg-white">
            <summary class="cursor-pointer list-none px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-600 marker:content-none [&::-webkit-details-marker]:hidden">
                Advanced · Page credentials
            </summary>
            <div class="grid gap-3 border-t border-slate-100 px-3 py-3 sm:grid-cols-2">
                <p class="sm:col-span-2 text-[11px] leading-5 text-slate-500">
                    Meta App Secret and webhook verify token are platform-managed (env). Shops only connect a Facebook Page.
                </p>

                <label class="block text-xs font-semibold text-slate-600">
                    Facebook Page ID
                    <input
                        type="text"
                        name="channels[messenger][page_id]"
                        value="{{ old('channels.messenger.page_id', $shopConnection->pageId()) }}"
                        class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm"
                        placeholder="Page ID"
                        autocomplete="off"
                    >
                </label>

                <label class="block text-xs font-semibold text-slate-600">
                    Page name
                    <input
                        type="text"
                        name="channels[messenger][page_name]"
                        value="{{ old('channels.messenger.page_name', $shopConnection->pageName()) }}"
                        class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm"
                        placeholder="Demo Auto Repair"
                        autocomplete="off"
                    >
                </label>

                <label class="block text-xs font-semibold text-slate-600 sm:col-span-2">
                    Page access token
                    <input
                        type="password"
                        name="channels[messenger][page_access_token]"
                        value=""
                        class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm"
                        placeholder="{{ $shopConnection->pageAccessToken() ? 'Saved — leave blank to keep' : 'Not configured' }}"
                        autocomplete="new-password"
                    >
                    <span class="mt-1 block text-[11px] font-normal leading-4 text-slate-500">
                        Long-lived Page token with <code class="text-[10px]">pages_messaging</code>. Stored encrypted. Never shown after save.
                        <a href="{{ $messengerArkademyUrl }}#page-access-token" class="font-semibold text-slate-600 underline">How to generate</a>
                    </span>
                </label>

                <label class="block text-xs font-semibold text-slate-600 sm:col-span-2">
                    Default outside-window message tag
                    <select
                        name="channels[messenger][outside_window_tag]"
                        class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm"
                    >
                        <option value="">None — require manual tag per send</option>
                        @foreach (MetaMessengerMessageTag::cases() as $tag)
                            <option
                                value="{{ $tag->value }}"
                                @selected(old('channels.messenger.outside_window_tag', $shopConnection->outsideWindowTag()?->value) === $tag->value)
                            >{{ $tag->label() }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-[11px] font-normal leading-4 text-slate-500">
                        Meta allows free-form replies for 24 hours after the customer’s last message.
                        <a href="{{ $messengerArkademyUrl }}#24-hour-window" class="font-semibold text-slate-600 underline">Policy &amp; advisor usage</a>
                    </span>
                </label>
            </div>
        </details>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-[11px] text-slate-500">
            Health check:
            <x-operations.learn.guide-link role="admin" article="comms-health-check" label="Communications health check" class="font-semibold text-slate-700" />
        </p>
        <button type="submit" class="h-9 rounded-sm bg-slate-950 px-4 text-xs font-bold uppercase tracking-wide text-white">
            Save channel settings
        </button>
    </div>
</form>
