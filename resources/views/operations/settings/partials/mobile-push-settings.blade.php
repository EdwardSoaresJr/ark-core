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
        'success' => 'alert alert-success',
        'warning' => 'alert alert-warning',
        'danger' => 'alert alert-danger',
        'muted' => 'alert alert-secondary',
    ];

    $pushTone = $mobilePush->isOperational() ? 'success' : ($mobilePush->enabled ? 'warning' : 'muted');
    $voiceTone = $arkVoiceConfigured ? 'success' : 'warning';
    $platformVoiceManaged = (bool) ($platformVoiceManaged ?? \App\Ark\Platform\Voice\ManagedVoiceGate::platformVoiceReady());
@endphp

<form
    method="POST"
    action="{{ route('operations.settings.shop.telephony.update') }}"
    class="d-flex flex-column gap-3"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="communications_tab" value="mobile">

    <div class="border rounded p-3 d-flex flex-column gap-3">
        <div class="border-bottom pb-2">
            <div class="subheader">ARK Mobile</div>
            <h4 class="mb-1">Staff app voice and push</h4>
            <p class="text-secondary small mb-0">
                Registered devices appear when staff log into the ARK Staff app. Voice extensions and ring targets are provisioned automatically - not edited here.
            </p>
        </div>

        <div class="row g-2">
            <div class="col-12 col-md-6">
                <div class="{{ $toneClasses[$voiceTone] ?? $toneClasses['muted'] }} mb-0 py-2 small h-100">
                    <div class="subheader">ARK Phone</div>
                    <div class="fw-bold">
                        @if ($platformVoiceManaged)
                            Managed in ARK Cloud
                        @else
                            {{ $arkVoiceConfigured ? 'Ready' : 'Not ready' }}
                        @endif
                    </div>
                    <div class="mt-1">
                        @if ($platformVoiceManaged)
                            Inbound Voice and phone credentials are configured under Phone settings in ARK Cloud. Device list and push below remain shop operational tooling.
                        @elseif ($arkVoiceConfigured)
                            Twilio Client is configured. In-app calls use the Companion call screen.
                        @else
                            Save Twilio Voice API Key + TwiML App below, or run <span class="font-monospace">php artisan ark:telephony:ensure-mobile-voice</span> on the server.
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="{{ $toneClasses[$pushTone] ?? $toneClasses['muted'] }} mb-0 py-2 small h-100">
                    <div class="subheader">Push notifications</div>
                    <div class="fw-bold">
                        @if ($mobilePush->isOperational())
                            Operational
                        @elseif ($mobilePush->enabled)
                            Enabled - server credentials missing
                        @else
                            Disabled for this shop
                        @endif
                    </div>
                    <div class="mt-1">{{ $mobilePush->transportSummary() }}</div>
                </div>
            </div>
        </div>

        @if ($mobileDeviceRows === [])
            <p class="alert alert-secondary small py-2 mb-0">
                No registered mobile devices yet. Staff must log into the ARK Staff app on a phone or tablet.
            </p>
        @else
            <div class="card card-sm">
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter card-table mb-0">
                        <thead>
                            <tr>
                                <th>Advisor · device</th>
                                <th>Platform</th>
                                <th>Ext</th>
                                <th>Voice</th>
                                <th>Push token</th>
                                <th>Version</th>
                                <th>Last seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mobileDeviceRows as $row)
                                <tr class="small">
                                    <td>
                                        <div class="fw-semibold text-truncate">{{ $row->advisorName }}</div>
                                        <div class="text-secondary text-truncate">{{ $row->deviceName }}</div>
                                    </td>
                                    <td class="text-truncate">{{ $row->platform }}</td>
                                    <td class="font-monospace">{{ $row->extension ?? '-' }}</td>
                                    <td @class([
                                        'fw-semibold',
                                        'text-success' => $row->voiceLive,
                                        'text-secondary' => ! $row->voiceLive && $row->voiceEnabled,
                                        'text-muted' => ! $row->voiceEnabled,
                                    ])>
                                        @if ($row->voiceLive)
                                            Live
                                        @elseif ($row->voiceEnabled)
                                            Offline
                                        @else
                                            Pending
                                        @endif
                                    </td>
                                    <td class="fw-semibold {{ $row->pushTokenRegistered ? 'text-success' : 'text-muted' }}">
                                        {{ $row->pushTokenRegistered ? 'Yes' : 'No' }}
                                    </td>
                                    <td class="font-monospace text-secondary text-truncate">{{ $row->appVersion ?? '-' }}</td>
                                    <td class="text-secondary text-truncate">{{ $row->lastSeenLabel ?? 'Never' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="small text-secondary mb-0">
                Voice <span class="fw-semibold">Live</span> means the app checked in within the last {{ \App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar::COVERAGE_PRESENCE_MINUTES }} minutes with an enabled mobile ring target.
                Extensions in the 8100 range are assigned automatically per device.
            </p>
        @endif
    </div>

    @if (! $platformVoiceManaged)
    <div class="border rounded p-3 d-flex flex-column gap-3">
        <div class="border-bottom pb-2">
            <div class="subheader">Twilio Client</div>
            <h4 class="mb-1">In-app calling credentials</h4>
            <p class="text-secondary small mb-0">
                Required for ARK Phone / Companion in-app voice after Twilio-native transport. Secrets are encrypted - leave blank to keep the current value.
            </p>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="d-block">
                    <span class="form-label">Voice API Key SID</span>
                    <input
                        type="text"
                        name="twilio_api_key_sid"
                        value="{{ old('twilio_api_key_sid', $settings->twilio_api_key_sid) }}"
                        class="form-control font-monospace"
                        placeholder="SK…"
                        autocomplete="off"
                    >
                </label>
            </div>
            <div class="col-12 col-md-6">
                <label class="d-block">
                    <span class="form-label">Voice API Key secret</span>
                    <input
                        type="password"
                        name="twilio_api_key_secret"
                        class="form-control font-monospace"
                        placeholder="{{ $hasStoredApiKeySecret ? 'Saved - leave blank to keep' : 'Shown once when created' }}"
                        autocomplete="new-password"
                    >
                </label>
            </div>
            <div class="col-12 col-md-6">
                <label class="d-block">
                    <span class="form-label">Voice TwiML App SID</span>
                    <input
                        type="text"
                        name="twilio_voice_twiml_app_sid"
                        value="{{ old('twilio_voice_twiml_app_sid', $settings->twilio_voice_twiml_app_sid) }}"
                        class="form-control font-monospace"
                        placeholder="AP…"
                        autocomplete="off"
                    >
                </label>
            </div>
            <div class="col-12 col-md-6">
                <label class="d-block">
                    <span class="form-label">Twilio FCM credential SID</span>
                    <input
                        type="text"
                        name="twilio_fcm_credential_sid"
                        value="{{ old('twilio_fcm_credential_sid', $settings->twilio_fcm_credential_sid) }}"
                        class="form-control font-monospace"
                        placeholder="CR…"
                        autocomplete="off"
                    >
                    <span class="form-hint d-block">Android inbound wake via Twilio Voice SDK. Auto-provisioned when Firebase service account is on the server.</span>
                </label>
            </div>
            <div class="col-12 col-md-6">
                <label class="d-block">
                    <span class="form-label">Twilio iOS VoIP push credential SID</span>
                    <input
                        type="text"
                        name="twilio_apns_voip_credential_sid"
                        value="{{ old('twilio_apns_voip_credential_sid', $settings->twilio_apns_voip_credential_sid) }}"
                        class="form-control font-monospace"
                        placeholder="CR…"
                        autocomplete="off"
                    >
                    <span class="form-hint d-block">iPhone locked-screen wake via PushKit. Apple: VoIP Services Certificate → export .p12 → cert.pem + key.pem. Twilio: Push Credentials → Type APN → Sandbox on for Debug → paste only the CR… SID here. Never paste .p12, PEM, or private key into ARK. Use a separate non-sandbox credential for TestFlight/Release.</span>
                </label>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-12 col-md-4">
                <div class="border rounded px-3 py-2 h-100">
                    <div class="subheader">TwiML App SID</div>
                    <div class="small fw-semibold {{ $telephonyHealth->mobileVoiceTwimlAppPresent() ? 'text-success' : 'text-warning' }}">
                        {{ $telephonyHealth->mobileVoiceTwimlAppPresent() ? 'Present' : 'Missing' }}
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="border rounded px-3 py-2 h-100">
                    <div class="subheader">iOS VoIP push SID</div>
                    <div class="small fw-semibold {{ $telephonyHealth->mobileVoiceIosVoipPushCredentialPresent() ? 'text-success' : 'text-warning' }}">
                        {{ $telephonyHealth->mobileVoiceIosVoipPushCredentialPresent() ? 'Present' : 'Missing' }}
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="border rounded px-3 py-2 h-100">
                    <div class="subheader">Client inbound</div>
                    <div class="small fw-semibold {{ $telephonyHealth->mobileVoiceClientInboundEnabled() ? 'text-success' : 'text-warning' }}">
                        {{ $telephonyHealth->mobileVoiceClientInboundEnabled() ? 'Enabled' : 'Blocked' }}
                    </div>
                </div>
            </div>
        </div>

        <div
            class="list-group"
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
            <div class="list-group-item py-2">
                <div class="subheader">TwiML App webhooks</div>
            </div>
            @foreach ($clientWebhookRows as $webhook)
                <button
                    type="button"
                    @click="copyUrl(@js($webhook['url']), @js($webhook['label']))"
                    class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 text-start"
                    title="{{ $webhook['hint'] }} - click to copy"
                >
                    <span class="subheader flex-shrink-0" style="width: 7rem">{{ $webhook['label'] }}</span>
                    <span class="flex-fill text-truncate font-monospace small" style="min-width: 0">{{ $webhook['url'] }}</span>
                    <span
                        class="subheader flex-shrink-0"
                        x-show="copiedLabel !== @js($webhook['label'])"
                    >Copy</span>
                    <span
                        class="subheader flex-shrink-0 text-success"
                        x-show="copiedLabel === @js($webhook['label'])"
                        x-cloak
                    >Copied</span>
                </button>
            @endforeach
        </div>
    </div>
    @else
    <div class="border rounded p-3 small text-secondary">
        <div class="fw-semibold text-body">In-app calling credentials</div>
        <p class="mt-1 mb-0">
            Twilio Client / TwiML App / VoIP push SIDs for Hosted Voice are managed in ARK Cloud - not edited here.
            <a href="https://cloud.arksms.com" class="fw-semibold" target="_blank" rel="noopener">Open ARK Cloud</a>
        </p>
    </div>
    @endif

    <div class="border rounded p-3 d-flex flex-column gap-3">
        <div class="border-bottom pb-2">
            <div class="subheader">Push</div>
            <h4 class="mb-1">Shop dispatch toggle</h4>
            <p class="text-secondary small mb-0">
                Firebase credentials are platform infrastructure - mounted on the server, not configured per shop.
            </p>
        </div>

        <div class="row g-2">
            <div class="col-12 col-md-4">
                <div class="border rounded px-3 py-2 h-100">
                    <div class="subheader">Platform credentials</div>
                    <div class="fw-bold">{{ $mobilePush->credentialsSourceLabel() }}</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="border rounded px-3 py-2 h-100">
                    <div class="subheader">Firebase project</div>
                    <div class="fw-bold font-monospace text-truncate">{{ $resolvedProjectId ?? '-' }}</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="border rounded px-3 py-2 h-100">
                    <div class="subheader">Device register API</div>
                    <div class="fw-bold">
                        {{ $mobilePush->isOperational() ? 'push_enabled: true' : 'push_enabled: false' }}
                    </div>
                </div>
            </div>
        </div>

        <label class="form-check form-switch mb-0">
            <input type="hidden" name="mobile_push[enabled]" value="0">
            <input
                type="checkbox"
                name="mobile_push[enabled]"
                value="1"
                @checked($pushEnabled)
                class="form-check-input"
            >
            <span class="form-check-label">
                <span class="fw-semibold">Dispatch push for this shop</span>
                <span class="form-hint d-block mt-0">When off, devices still register but ARK does not send push packets for this shop.</span>
            </span>
        </label>
    </div>

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            Save mobile settings
        </button>
    </div>
</form>
