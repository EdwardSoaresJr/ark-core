@php
    /** @var \App\Ark\Operations\Settings\ShopSettings $settings */
    /** @var \App\Ark\Operations\Telephony\TelephonyHealth $telephonyHealth */

    $mobilePush = \App\Ark\Mobile\Push\MobilePushSettings::fromShopSettings($settings);
    $mobileDevices = \App\Ark\Mobile\MobileStaffDevicesProjection::forCurrentShop();
    $mobileDeviceRows = $mobileDevices->rows();
    $arkVoiceConfigured = $mobileDevices->arkVoiceConfigured();
    $pushEnabled = (bool) old('mobile_push.enabled', $mobilePush->enabled);
    $resolvedProjectId = $mobilePush->resolvedProjectId();
        $clientWebhookRows = $telephonyHealth->mobileVoiceClientWebhookRows();
    $mobileDevices = \App\Ark\Mobile\MobileStaffDevicesProjection::forCurrentShop();
    $mobileDeviceRows = $mobileDevices->rows();
    $arkVoiceConfigured = $mobileDevices->arkVoiceConfigured();
    $pushEnabled = (bool) old('mobile_push.enabled', $mobilePush->enabled);
    $resolvedProjectId = $mobilePush->resolvedProjectId();

    $toneClasses = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900',
        'muted' => 'border-slate-200 bg-slate-50 text-slate-700',
    ];

    $pushTone = $mobilePush->isOperational() ? 'success' : ($mobilePush->enabled ? 'warning' : 'muted');
    $voiceTone = $arkVoiceConfigured ? 'success' : 'warning';
@endphp

<form
    method="POST"
    action="{{ route('operations.settings.shop.telephony.update') }}"
    class="space-y-3"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="communications_tab" value="mobile">

    <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
        <div class="border-b border-slate-200 pb-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">ARK Mobile</p>
            <h3 class="mt-1 text-sm font-black text-slate-950">Staff app voice and push</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Registered devices appear when staff log into the ARK Staff app. Voice extensions and ring targets are provisioned automatically — not edited here.
            </p>
        </div>

        <div class="grid gap-2 sm:grid-cols-2">
            <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$voiceTone] ?? $toneClasses['muted'] }}">
                <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">ARK Phone</p>
                <p class="mt-1 text-sm font-black">
                    {{ $arkVoiceConfigured ? 'Ready' : 'Not ready' }}
                </p>
                <p class="mt-1 text-[11px] leading-4 opacity-80">
                    @if ($arkVoiceConfigured)
                        In-app voice is ready.
                    @else
                        In-app voice requires a voice transport implementation. Stock ARK Core does not ship with one configured.
                    @endif
                </p>
            </div>
            <div class="rounded-sm border px-3 py-2 {{ $toneClasses[$pushTone] ?? $toneClasses['muted'] }}">
                <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">Push notifications</p>
                <p class="mt-1 text-sm font-black">
                    @if ($mobilePush->isOperational())
                        Operational
                    @elseif ($mobilePush->enabled)
                        Enabled — server credentials missing
                    @else
                        Disabled for this shop
                    @endif
                </p>
                <p class="mt-1 text-[11px] leading-4 opacity-80">{{ $mobilePush->transportSummary() }}</p>
            </div>
        </div>

        @if ($mobileDeviceRows === [])
            <p class="rounded-sm border border-dashed border-slate-300 bg-white px-3 py-2 text-xs text-slate-600">
                No registered mobile devices yet. Staff must log into the ARK Staff app on a phone or tablet.
            </p>
        @else
            <div class="overflow-x-auto rounded-sm border border-slate-200 bg-white">
                <div class="min-w-[48rem]">
                    <div class="grid grid-cols-[1.2fr_1fr_5rem_5rem_6rem_5rem_6rem] gap-x-2 border-b border-slate-200 bg-slate-50 px-2 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                        <span>Advisor · device</span>
                        <span>Platform</span>
                        <span>Ext</span>
                        <span>Voice</span>
                        <span>Push token</span>
                        <span>Version</span>
                        <span>Last seen</span>
                    </div>
                    @foreach ($mobileDeviceRows as $row)
                        <div class="grid grid-cols-[1.2fr_1fr_5rem_5rem_6rem_5rem_6rem] items-center gap-x-2 border-b border-slate-100 px-2 py-1.5 text-xs last:border-b-0">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ $row->advisorName }}</p>
                                <p class="truncate text-[11px] text-slate-500">{{ $row->deviceName }}</p>
                            </div>
                            <span class="truncate text-slate-700">{{ $row->platform }}</span>
                            <span class="font-mono text-[11px] text-slate-800">{{ $row->extension ?? '—' }}</span>
                            <span @class([
                                'text-[11px] font-semibold',
                                'text-emerald-700' => $row->voiceLive,
                                'text-slate-500' => ! $row->voiceLive && $row->voiceEnabled,
                                'text-slate-400' => ! $row->voiceEnabled,
                            ])>
                                @if ($row->voiceLive)
                                    Live
                                @elseif ($row->voiceEnabled)
                                    Offline
                                @else
                                    Pending
                                @endif
                            </span>
                            <span class="text-[11px] font-semibold {{ $row->pushTokenRegistered ? 'text-emerald-700' : 'text-slate-400' }}">
                                {{ $row->pushTokenRegistered ? 'Yes' : 'No' }}
                            </span>
                            <span class="truncate font-mono text-[10px] text-slate-600">{{ $row->appVersion ?? '—' }}</span>
                            <span class="truncate text-[11px] text-slate-500">{{ $row->lastSeenLabel ?? 'Never' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <p class="text-[11px] leading-4 text-slate-500">
                Voice <span class="font-semibold">Live</span> means the app checked in within the last {{ \App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar::COVERAGE_PRESENCE_MINUTES }} minutes with an enabled mobile ring target.
                Extensions in the 8100 range are assigned automatically per device.
            </p>
        @endif
    </div>

        <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
        <div class="border-b border-slate-200 pb-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">In-app voice</p>
            <h3 class="mt-1 text-sm font-black text-slate-950">Voice transport</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                In-app calling requires a voice transport implementation. Stock ARK Core does not ship with voice credentials or provider fields.
            </p>
        </div>
    </div>

<div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
            <h3 class="mt-1 text-sm font-black text-slate-950">Shop dispatch toggle</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Firebase credentials are platform infrastructure — mounted on the server, not configured per shop.
            </p>
        </div>

        <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-sm border border-slate-200 bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Platform credentials</p>
                <p class="mt-1 text-sm font-black text-slate-950">{{ $mobilePush->credentialsSourceLabel() }}</p>
            </div>
            <div class="rounded-sm border border-slate-200 bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Firebase project</p>
                <p class="mt-1 truncate font-mono text-sm font-black text-slate-950">{{ $resolvedProjectId ?? '—' }}</p>
            </div>
            <div class="rounded-sm border border-slate-200 bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Device register API</p>
                <p class="mt-1 text-sm font-black text-slate-950">
                    {{ $mobilePush->isOperational() ? 'push_enabled: true' : 'push_enabled: false' }}
                </p>
            </div>
        </div>

        <label class="flex items-start gap-2 text-sm text-slate-800">
            <input type="hidden" name="mobile_push[enabled]" value="0">
            <input
                type="checkbox"
                name="mobile_push[enabled]"
                value="1"
                @checked($pushEnabled)
                class="mt-0.5 rounded border-slate-300"
            >
            <span>
                <span class="font-semibold text-slate-900">Dispatch push for this shop</span>
                <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">When off, devices still register but ARK does not send push packets for this shop.</span>
            </span>
        </label>
    </div>

    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
        Save mobile settings
    </button>
</form>
