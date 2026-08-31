@php
    use App\Ark\Operations\Messaging\Messenger\MessengerChannelConnection;
    use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;

    $channelConnection = MessengerChannelConnection::forCurrentShop();
    $shopConnection = $channelConnection->shopConnection();
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
                Messenger appears as a conversation channel in ARK. Outbound and inbound transport is not bundled in Core.
            </p>
        </div>

        <div class="rounded-sm border px-3 py-2.5 {{ $webhookToneClasses[$statusTone] ?? $webhookToneClasses['muted'] }}">
            <p class="text-xs font-bold uppercase tracking-wide">Messenger</p>
            <p class="mt-1 text-sm font-semibold">{{ $channelConnection->statusLabel() }}</p>
            <p class="mt-2 text-[11px] font-medium leading-5 text-slate-700">
                Messenger outbound is not configured. Historical Messenger conversations remain readable when linked to customers.
            </p>
        </div>

        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <input
                type="checkbox"
                name="channels[messenger][enabled]"
                value="1"
                class="rounded border-slate-300 text-slate-900"
                @checked(old('channels.messenger.enabled', $shopConnection->isEnabled()))
            >
            Show Messenger in inbound queue filters
            <span class="text-[11px] font-normal text-slate-500">— display only; transport is not active</span>
        </label>

        <label class="block text-xs font-semibold text-slate-600">
            Default outside-window message tag
            <select
                name="channels[messenger][outside_window_tag]"
                class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm"
            >
                <option value="">None</option>
                @foreach (MetaMessengerMessageTag::cases() as $tag)
                    <option
                        value="{{ $tag->value }}"
                        @selected(old('channels.messenger.outside_window_tag', $shopConnection->outsideWindowTag()?->value) === $tag->value)
                    >{{ $tag->label() }}</option>
                @endforeach
            </select>
            <span class="mt-1 block text-[11px] font-normal leading-4 text-slate-500">
                Reserved for future Messenger transport configuration.
            </span>
        </label>
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
