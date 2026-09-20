@php
    /** @var \App\Ark\Operations\Settings\ShopSettings $settings */
    /** @var \App\Ark\Operations\Telephony\TelephonyHealth $telephonyHealth */

    $mobilePush = \App\Ark\Mobile\Push\MobilePushSettings::fromShopSettings($settings);
    $mobileDevices = \App\Ark\Mobile\MobileStaffDevicesProjection::forCurrentShop();
    $mobileDeviceRows = $mobileDevices->rows();
    $arkVoiceConfigured = $mobileDevices->arkVoiceConfigured();
    $pushEnabled = (bool) old('mobile_push.enabled', $mobilePush->enabled);
    $resolvedProjectId = $mobilePush->resolvedProjectId();
    $hasStoredApiKeySecret = filled($settings->twilio_api_key_secret);
    $clientWebhookRows = $telephonyHealth->mobileVoiceClientWebhookRows();

    $toneClasses = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900',
        'muted' => 'border-slate-200 bg-slate-50 text-slate-700',
    ];

    $pushTone = $mobilePush->isOperational() ? 'success' : ($mobilePush->enabled ? 'warning' : 'muted');
    $voiceTone = $arkVoiceConfigured ? 'success' : 'warning';
    $platformVoiceManaged = (bool) ($platformVoiceManaged ?? \App\Ark\Platform\Voice\ManagedVoiceGate::platformVoiceReady());
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
                    @if ($platformVoiceManaged)
                        Managed in ARK Cloud
                    @else
                        {{ $arkVoiceConfigured ? 'Ready' : 'Not ready' }}
                    @endif
                </p>
                <p class="mt-1 text-[11px] leading-4 opacity-80">
                    @if ($platformVoiceManaged)
                        Inbound Voice and phone credentials are configured under Phone settings in ARK Cloud. Device list and push below remain shop operational tooling.
                    @elseif ($arkVoiceConfigured)
                        Twilio Client is configured. In-app calls use the Companion call screen.
                    @else
                        Save Twilio Voice API Key + TwiML App below, or run <span class="font-mono">php artisan ark:telephony:ensure-mobile-voice</span> on the server.
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

    @if (! $platformVoiceManaged)
    <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
        <div class="border-b border-slate-200 pb-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Twilio Client</p>
            <h3 class="mt-1 text-sm font-black text-slate-950">In-app calling credentials</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Required for ARK Phone / Companion in-app voice after Twilio-native transport. Secrets are encrypted — leave blank to keep the current value.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Voice API Key SID</span>
                <input
                    type="text"
                    name="twilio_api_key_sid"
                    value="{{ old('twilio_api_key_sid', $settings->twilio_api_key_sid) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="SK…"
                    autocomplete="off"
                >
            </label>
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Voice API Key secret</span>
                <input
                    type="password"
                    name="twilio_api_key_secret"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="{{ $hasStoredApiKeySecret ? 'Saved — leave blank to keep' : 'Shown once when created' }}"
                    autocomplete="new-password"
                >
            </label>
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Voice TwiML App SID</span>
                <input
                    type="text"
                    name="twilio_voice_twiml_app_sid"
                    value="{{ old('twilio_voice_twiml_app_sid', $settings->twilio_voice_twiml_app_sid) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="AP…"
                    autocomplete="off"
                >
            </label>
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Twilio FCM credential SID</span>
                <input
                    type="text"
                    name="twilio_fcm_credential_sid"
                    value="{{ old('twilio_fcm_credential_sid', $settings->twilio_fcm_credential_sid) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="CR…"
                    autocomplete="off"
                >
                <span class="mt-1 block text-[11px] leading-4 text-slate-500">Android inbound wake via Twilio Voice SDK. Auto-provisioned when Firebase service account is on the server.</span>
            </label>
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Twilio iOS VoIP push credential SID</span>
                <input
                    type="text"
                    name="twilio_apns_voip_credential_sid"
                    value="{{ old('twilio_apns_voip_credential_sid', $settings->twilio_apns_voip_credential_sid) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="CR…"
                    autocomplete="off"
                >
                <span class="mt-1 block text-[11px] leading-4 text-slate-500">iPhone locked-screen wake via PushKit. Apple: VoIP Services Certificate → export .p12 → cert.pem + key.pem. Twilio: Push Credentials → Type APN → Sandbox on for Debug → paste only the CR… SID here. Never paste .p12, PEM, or private key into ARK. Use a separate non-sandbox credential for TestFlight/Release.</span>
            </label>
        </div>

        <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-sm border border-slate-200 bg-white px-2.5 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">TwiML App SID</p>
                <p class="mt-0.5 text-xs font-semibold {{ $telephonyHealth->mobileVoiceTwimlAppPresent() ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $telephonyHealth->mobileVoiceTwimlAppPresent() ? 'Present' : 'Missing' }}
                </p>
            </div>
            <div class="rounded-sm border border-slate-200 bg-white px-2.5 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">iOS VoIP push SID</p>
                <p class="mt-0.5 text-xs font-semibold {{ $telephonyHealth->mobileVoiceIosVoipPushCredentialPresent() ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $telephonyHealth->mobileVoiceIosVoipPushCredentialPresent() ? 'Present' : 'Missing' }}
                </p>
            </div>
            <div class="rounded-sm border border-slate-200 bg-white px-2.5 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Client inbound</p>
                <p class="mt-0.5 text-xs font-semibold {{ $telephonyHealth->mobileVoiceClientInboundEnabled() ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $telephonyHealth->mobileVoiceClientInboundEnabled() ? 'Enabled' : 'Blocked' }}
                </p>
            </div>
        </div>

        <div
            class="divide-y divide-slate-200 rounded-sm border border-slate-200 bg-white"
            x-data="{
                copiedLabel: null,
                async copyUrl(url, label) {
                    try {
                        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                            await navigator.clipboard.writeText(url);
                        } else {
                            window.prompt('Copy URL:', url);
                            return;
                        }
                        this.copiedLabel = label;
                        window.setTimeout(() => { this.copiedLabel = null; }, 2000);
                    } catch (error) {
                        window.prompt('Copy URL:', url);
                    }
                },
            }"
        >
            <p class="px-2 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">TwiML App webhooks</p>
            @foreach ($clientWebhookRows as $webhook)
                <button
                    type="button"
                    @click="copyUrl(@js($webhook['url']), @js($webhook['label']))"
                    class="flex w-full items-center gap-2 px-2 py-1.5 text-left transition hover:bg-slate-50"
                    title="{{ $webhook['hint'] }} — click to copy"
                >
                    <span class="w-28 shrink-0 text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $webhook['label'] }}</span>
                    <span class="min-w-0 flex-1 truncate font-mono text-[11px] text-slate-700">{{ $webhook['url'] }}</span>
                    <span
                        class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-slate-400"
                        x-show="copiedLabel !== @js($webhook['label'])"
                    >Copy</span>
                    <span
                        class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-emerald-700"
                        x-show="copiedLabel === @js($webhook['label'])"
                        x-cloak
                    >Copied</span>
                </button>
            @endforeach
        </div>
    </div>
    @else
    <div class="rounded-sm border border-slate-200 bg-slate-50/60 px-3 py-3 text-xs leading-5 text-slate-600">
        <p class="font-semibold text-slate-950">In-app calling credentials</p>
        <p class="mt-1">
            Twilio Client / TwiML App / VoIP push SIDs for Hosted Voice are managed in ARK Cloud — not edited here.
            <a href="https://cloud.arksms.com" class="font-semibold underline" target="_blank" rel="noopener">Open ARK Cloud</a>
        </p>
    </div>
    @endif

    <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
        <div class="border-b border-slate-200 pb-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Push</p>
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
