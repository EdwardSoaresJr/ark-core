@php
    /** @var \App\Ark\Operations\Settings\ShopSettings $settings */
    /** @var \App\Ark\Operations\Telephony\TelephonyHealth $telephonyHealth */
    /** @var \App\Ark\Operations\Messaging\MessagingHealth $messagingHealth */
    /** @var \App\Ark\Operations\Messaging\Messenger\MessengerHealth $messengerHealth */
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Telephony\TelephonyEndpoint> $telephonyEndpoints */
    /** @var array<int, \App\Ark\Operations\Telephony\TelephonyEndpointType> $telephonyEndpointTypes */
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Telephony\TelephonyExtension> $telephonyExtensions */
    /** @var array<int, \App\Ark\Operations\Telephony\TelephonyExtensionDeviceType> $telephonyExtensionDeviceTypes */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $staff */
    $lastCall = $telephonyHealth->lastIncomingCall();
    $lastVoiceWebhookAt = $telephonyHealth->lastVoiceSignalAt();
    $lastSmsWebhookAt = $messagingHealth->lastWebhookAt();
    $messengerHealth = \App\Ark\Operations\Messaging\Messenger\MessengerHealth::forCurrentShop();
    $lastMessengerWebhookAt = $messengerHealth->lastWebhookAt();
    $operationalNotes = array_merge(
        $telephonyHealth->operationalNotes(),
        $messagingHealth->operationalNotes(),
        $messengerHealth->operationalNotes(),
    );

    $toneClasses = [
        'success' => 'alert alert-success',
        'warning' => 'alert alert-warning',
        'danger' => 'alert alert-danger',
        'muted' => 'alert alert-secondary',
    ];

    $callFlow = \App\Ark\Operations\Telephony\TelephonyCallFlowSettings::fromShopSettings($settings);
    $callFlowConfig = $callFlow->toArray();
    $savedCallerRingTone = (string) ($callFlowConfig['caller_ring_tone'] ?? 'us');
    $callerRingIsPromo = \App\Ark\Operations\Telephony\TelephonyCallerRingtone::isPromoUrl($savedCallerRingTone);
    $callerRingMode = old('telephony_call_flow.caller_ring_audio_mode', $callerRingIsPromo ? 'promo' : 'standard');
    $callerRingPromoUrl = old('telephony_call_flow.caller_ring_promo_url', $callerRingIsPromo ? $savedCallerRingTone : '');
    $ringSchedules = \App\Ark\Operations\Telephony\TelephonyRingSchedule::cases();
    $weekdayLabels = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    $endpointRows = old('endpoints', $telephonyEndpoints
        ->reject(fn ($endpoint) => $endpoint->type === \App\Ark\Operations\Telephony\TelephonyEndpointType::MobileApp)
        ->map(fn ($endpoint) => [
        'name' => $endpoint->name,
        'type' => $endpoint->type->value,
        'destination' => $endpoint->destination,
        'user_id' => $endpoint->user_id ? (string) $endpoint->user_id : '',
        'ring_schedule' => $endpoint->ring_schedule?->value ?? 'always',
        'ring_delay_seconds' => $endpoint->ring_delay_seconds ?? 0,
        'presence_timeout_minutes' => $endpoint->presence_timeout_minutes ?? 30,
        'enabled' => $endpoint->enabled,
    ])->values()->all());

    $communicationsTab = request()->query('communications-tab', 'general');

    if ($communicationsTab === 'channels') {
        $communicationsTab = 'messenger';
    }

    $allowedCommunicationsTabs = ['general', 'email', 'messenger', 'hours', 'recording', 'ring', 'mobile'];

    if (! in_array($communicationsTab, $allowedCommunicationsTabs, true)) {
        $communicationsTab = 'general';
    }

    $telephonyExtensions = $telephonyExtensions ?? collect();
    $telephonyExtensionDeviceTypes = $telephonyExtensionDeviceTypes ?? \App\Ark\Operations\Telephony\TelephonyExtensionDeviceType::cases();
    $platformVoiceManaged = (bool) ($platformVoiceManaged ?? \App\Ark\Platform\Voice\ManagedVoiceGate::platformVoiceReady());
@endphp

<div class="card-header">
    <div>
        <div class="subheader">Communications</div>
        <h2 class="card-title">Phone, SMS, and email</h2>
        <div class="card-subtitle">
            Business phone, SMS, email, Messenger, and call routing.
        </div>
    </div>
</div>

<div class="card-header">
    <ul class="nav nav-tabs card-header-tabs flex-wrap">
        @foreach ([
            'general' => 'General',
            'email' => 'Email',
            'messenger' => 'Messenger',
            'hours' => 'Hours',
            'recording' => 'Recording',
            'ring' => 'Call routing',
            'mobile' => 'Mobile',
        ] as $tabKey => $tabLabel)
            <li class="nav-item">
                <a
                    href="{{ route('operations.settings.shop.edit', ['section' => 'communications', 'communications-tab' => $tabKey]) }}"
                    @class(['nav-link', 'active' => $communicationsTab === $tabKey])
                    @if ($communicationsTab === $tabKey) aria-current="page" @endif
                >{{ $tabLabel }}</a>
            </li>
        @endforeach
    </ul>
</div>

<div class="card-body d-flex flex-column gap-3">
    @if ($platformVoiceManaged)
        <div class="alert alert-secondary small mb-0">
            <div class="fw-medium">Phone settings are managed in ARK Cloud</div>
            <div class="mt-1">
                Inbound schedule, SIP, fallback, recording disclosure, and after-hours greetings run on Platform Voice.
                Core keeps call history and shop workflow - not phone configuration.
                <a href="https://cloud.arksms.com" target="_blank" rel="noopener">Open ARK Cloud</a>
            </div>
        </div>
    @endif

    @if ($platformVoiceManaged && in_array($communicationsTab, ['hours', 'recording', 'ring'], true))
        <div class="alert alert-warning small mb-0">
            <div class="fw-medium">This phone control moved to ARK Cloud</div>
            <div class="mt-1">
                @if ($communicationsTab === 'hours')
                    Voice open/closed schedule is configured under Phone settings in ARK Cloud. Shop hours for appointments stay in Operations settings where needed - they do not drive live call routing for Hosted Voice.
                @elseif ($communicationsTab === 'recording')
                    Recording, disclosure, and greetings for inbound calls are configured in ARK Cloud Phone settings. Call-intelligence API keys below remain shop operational tooling when used.
                @else
                    SIP destinations and PSTN fallback for Hosted Voice are configured in ARK Cloud Phone settings. Local endpoint lists here do not control Platform dial.
                @endif
            </div>
            <div class="mt-2">
                <a href="https://cloud.arksms.com" target="_blank" rel="noopener">Manage phone in ARK Cloud</a>
            </div>
        </div>
    @endif

    @if ($communicationsTab === 'email')
        @include('operations.settings.partials.communications-email-settings', [
            'settings' => $settings,
        ])
    @elseif ($communicationsTab === 'messenger')
        @include('operations.settings.partials.communications-channels-settings', ['settings' => $settings])
    @elseif ($communicationsTab === 'mobile')
        @include('operations.settings.partials.mobile-push-settings', [
            'settings' => $settings,
            'telephonyHealth' => $telephonyHealth,
            'platformVoiceManaged' => $platformVoiceManaged,
        ])
    @elseif ($platformVoiceManaged && in_array($communicationsTab, ['hours', 'recording', 'ring'], true))
        {{-- Hosted Voice: managed phone config is Platform-only. Banner above is the editor. --}}
    @else
    <form
        method="POST"
        action="{{ route('operations.settings.shop.telephony.update') }}"
        class="d-flex flex-column gap-3"
        x-data="{
            endpoints: @js($endpointRows),
            callerRingMode: @js($callerRingMode),
            callerRingPromoUrl: @js($callerRingPromoUrl),
            validationErrors: @js($errors->getMessages()),
            staffMembers: @js($staff->map(fn ($member) => [
                'id' => (string) $member->id,
                'name' => $member->name,
                'phone' => $member->display_phone,
                'hasPhone' => filled($member->phone),
            ])->values()->all()),
            addEndpoint() {
                this.endpoints.push({ name: '', type: 'cell', destination: '', user_id: '', ring_schedule: 'always', ring_delay_seconds: 0, presence_timeout_minutes: 30, enabled: true });
            },
            removeEndpoint(index) {
                this.endpoints.splice(index, 1);
            },
            staffHasPhone(userId) {
                const member = this.staffMembers.find((entry) => entry.id === String(userId));
                return member?.hasPhone ?? false;
            },
            endpointError(index, field) {
                const messages = this.validationErrors[`endpoints.${index}.${field}`];
                return Array.isArray(messages) ? messages[0] : null;
            },
            syncCellEndpoint(endpoint) {
                if (endpoint.type !== 'cell' || ! endpoint.user_id) {
                    return;
                }
                const member = this.staffMembers.find((entry) => entry.id === String(endpoint.user_id));
                if (member && ! endpoint.name) {
                    endpoint.name = `${member.name} Cell`;
                }
            },
        }"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="communications_tab" value="{{ $communicationsTab }}">

        @include('operations.settings.partials.communications-tab-alert', ['communicationsTab' => $communicationsTab])

        @if ($communicationsTab === 'general')
        <div class="d-flex flex-column gap-3">
            @include('operations.settings.partials.communications-general-overview', [
                'telephonyHealth' => $telephonyHealth,
                'messagingHealth' => $messagingHealth,
                'messengerHealth' => $messengerHealth,
                'lastCall' => $lastCall,
                'lastVoiceWebhookAt' => $lastVoiceWebhookAt,
                'lastSmsWebhookAt' => $lastSmsWebhookAt,
                'lastMessengerWebhookAt' => $lastMessengerWebhookAt,
                'operationalNotes' => $operationalNotes,
                'toneClasses' => $toneClasses,
            ])

            @include('operations.settings.partials.quick-reply-templates-settings')

            @include('operations.settings.partials.message-actions-settings', ['settings' => $settings])

            <div class="border rounded p-3">
                <div class="border-bottom pb-3 mb-3">
                    <div class="subheader">Texting</div>
                    <p class="text-secondary small mb-0 mt-1">
                        Customer texts are sent through ARK Texting when this shop is connected.
                        This shop does not store a messaging provider account.
                    </p>
                    <p class="small fw-medium mb-0 mt-2">
                        @if (($shopIntegrations ?? null)?->messagingConfigured())
                            ARK Texting is connected.
                        @else
                            ARK Texting is not connected. The composer still works; messages will not send until the service is connected.
                            @unless (\App\Ark\Runtime\DemoInstall::isDemo())
                                <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}">Connect in ARK Platform</a>
                            @endunless
                        @endif
                    </p>
                </div>

                @if ($platformVoiceManaged)
                    <div class="small text-secondary" style="max-width: 28rem">
                        <div class="subheader">Business number</div>
                        <p class="mb-0 mt-1">
                            Shop line is provisioned in ARK Cloud Phone settings
                            @if (filled($settings->telephony_inbound_number))
                                <span class="font-monospace text-body">({{ $settings->telephony_inbound_number }} - display only)</span>
                            @endif
                            .
                            <a href="https://cloud.arksms.com" target="_blank" rel="noopener">Open ARK Cloud</a>
                        </p>
                    </div>
                @else
                    <div style="max-width: 28rem">
                        <label for="settings-telephony-inbound-number" class="form-label">Business number</label>
                        <input
                            id="settings-telephony-inbound-number"
                            type="text"
                            name="telephony_inbound_number"
                            value="{{ old('telephony_inbound_number', $settings->telephony_inbound_number) }}"
                            class="form-control"
                            placeholder="+1 (719) 555-0100"
                        >
                        <div class="form-hint">The number customers call and see on outbound shop calls.</div>
                    </div>
                @endif
            </div>

            <div
                x-data="{
                    phone: '{{ old('test_phone', '7195551234') }}',
                    testing: false,
                    testMessage: '',
                    testTone: 'muted',
                    async testIncomingCall() {
                        this.testing = true;
                        this.testMessage = '';

                        try {
                            const response = await fetch('{{ route('operations.settings.telephony.test-incoming-call') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                    Accept: 'application/json',
                                },
                                body: JSON.stringify({ phone: this.phone }),
                            });

                            const payload = await response.json();

                            if (! response.ok) {
                                this.testTone = 'danger';
                                this.testMessage = payload.message ?? 'Test call failed.';
                                return;
                            }

                            this.testTone = 'success';
                            this.testMessage = payload.matched
                                ? `Screen pop fired for matched customer #${payload.customer_id}.`
                                : 'Screen pop fired for unknown caller.';
                        } catch (error) {
                            this.testTone = 'danger';
                            this.testMessage = 'Test call failed to reach ARK.';
                        } finally {
                            this.testing = false;
                        }
                    },
                }"
                class="border rounded p-3 d-flex flex-column gap-2"
            >
                <div>
                    <div class="subheader">Operational test</div>
                    <h3 class="mb-1">Test incoming call</h3>
                    <p class="text-secondary small mb-0">
                        Fires the incoming-call banner without placing a real phone call.
                    </p>
                </div>

                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div class="flex-fill" style="min-width: 12rem; max-width: 20rem">
                        <label for="settings-telephony-test-phone" class="form-label">Caller phone</label>
                        <input
                            id="settings-telephony-test-phone"
                            type="text"
                            x-model="phone"
                            class="form-control"
                            placeholder="7195551234"
                        >
                    </div>

                    <button
                        type="button"
                        @click="testIncomingCall()"
                        :disabled="testing"
                        class="btn"
                    >
                        <span x-text="testing ? 'Testing…' : 'Test incoming call'"></span>
                    </button>
                </div>

                <div
                    x-show="testMessage"
                    x-text="testMessage"
                    class="alert small fw-medium mb-0 py-2"
                    :class="{
                        'alert-success': testTone === 'success',
                        'alert-danger': testTone === 'danger',
                        'alert-secondary': testTone === 'muted',
                    }"
                    role="status"
                ></div>
            </div>
        </div>
        @endif

        @if ($communicationsTab === 'hours')
        <div class="border rounded p-3 d-flex flex-column gap-3">
            <div class="border-bottom pb-2">
                <div class="subheader">Call hours</div>
                <p class="text-secondary small mb-0 mt-1">Open hours control when inbound calls ring the shop. Closed hours go to voicemail.</p>
            </div>
            <div class="row g-2">
                <div class="col-12 col-sm-4">
                    <label for="settings-shop-timezone" class="form-label">Timezone</label>
                    <input
                        id="settings-shop-timezone"
                        type="text"
                        name="shop_timezone"
                        value="{{ old('shop_timezone', $settings->shop_timezone) }}"
                        required
                        class="form-control"
                    >
                </div>
                <div class="col-12 col-sm-4">
                    <label for="settings-dial-timeout" class="form-label">Ring timeout (seconds)</label>
                    <input
                        id="settings-dial-timeout"
                        type="number"
                        min="10"
                        max="60"
                        name="telephony_call_flow[dial_timeout_seconds]"
                        value="{{ old('telephony_call_flow.dial_timeout_seconds', $callFlowConfig['dial_timeout_seconds']) }}"
                        class="form-control"
                    >
                </div>
                <div class="col-12 col-sm-4">
                    <label for="settings-owned-popup-timeout" class="form-label">Owned popup dismiss (seconds)</label>
                    <input
                        id="settings-owned-popup-timeout"
                        type="number"
                        min="3"
                        max="60"
                        name="telephony_call_flow[owned_popup_timeout_seconds]"
                        value="{{ old('telephony_call_flow.owned_popup_timeout_seconds', $callFlowConfig['owned_popup_timeout_seconds']) }}"
                        class="form-control"
                    >
                </div>
            </div>
            <p class="text-secondary small mb-0">When another advisor owns the call, other popups auto-dismiss after this many seconds.</p>

            <div class="border rounded p-3 d-flex flex-column gap-2">
                <div class="subheader">Advisor comms accountability</div>
                <label class="form-check mb-0">
                    <input type="hidden" name="telephony_call_flow[comms_attention_gate_enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[comms_attention_gate_enabled]"
                        value="1"
                        @checked(old('telephony_call_flow.comms_attention_gate_enabled', $callFlowConfig['comms_attention_gate_enabled'] ?? true))
                        class="form-check-input"
                    >
                    <span class="form-check-label">Block other ARK pages until the Work communications queue is cleared (Mark Handled / Mark Read / Reply). Incoming call and text popups still appear either way.</span>
                </label>
                <label class="form-check mb-0">
                    <input type="hidden" name="telephony_call_flow[comms_escalation_enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[comms_escalation_enabled]"
                        value="1"
                        @checked(old('telephony_call_flow.comms_escalation_enabled', $callFlowConfig['comms_escalation_enabled'] ?? true))
                        class="form-check-input"
                    >
                    <span class="form-check-label">Text all active advisors when calls, texts, or website leads stay unhandled (requires advisor phone on staff profile).</span>
                </label>
                <div class="row g-2">
                    <div class="col-12 col-sm-6">
                        <label for="settings-escalation-delay" class="form-label">Escalation delay (minutes)</label>
                        <input
                            id="settings-escalation-delay"
                            type="number"
                            min="1"
                            max="30"
                            name="telephony_call_flow[comms_escalation_delay_minutes]"
                            value="{{ old('telephony_call_flow.comms_escalation_delay_minutes', $callFlowConfig['comms_escalation_delay_minutes'] ?? 3) }}"
                            class="form-control"
                        >
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="settings-escalation-cooldown" class="form-label">Escalation cooldown (minutes)</label>
                        <input
                            id="settings-escalation-cooldown"
                            type="number"
                            min="5"
                            max="240"
                            name="telephony_call_flow[comms_escalation_cooldown_minutes]"
                            value="{{ old('telephony_call_flow.comms_escalation_cooldown_minutes', $callFlowConfig['comms_escalation_cooldown_minutes'] ?? 30) }}"
                            class="form-control"
                        >
                    </div>
                </div>
                <label class="form-check mb-0">
                    <input type="hidden" name="telephony_call_flow[comms_browser_notifications_enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[comms_browser_notifications_enabled]"
                        value="1"
                        @checked(old('telephony_call_flow.comms_browser_notifications_enabled', $callFlowConfig['comms_browser_notifications_enabled'] ?? true))
                        class="form-check-input"
                    >
                    <span class="form-check-label">Browser notifications + chime when ARK tab is open (advisor must allow notifications once).</span>
                </label>
            </div>

            <div class="border rounded p-3 d-flex flex-column gap-2">
                <div class="subheader">Missed Call Rescue</div>
                <p class="text-secondary small mb-0">Automatically text callers after a missed inbound call. Uses Conversation (system SMS) - not a separate inbox.</p>
                <label class="form-check mb-0">
                    <input type="hidden" name="telephony_call_flow[missed_call_rescue_enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[missed_call_rescue_enabled]"
                        value="1"
                        @checked(old('telephony_call_flow.missed_call_rescue_enabled', $callFlowConfig['missed_call_rescue_enabled'] ?? false))
                        class="form-check-input"
                    >
                    <span class="form-check-label">Enable missed-call SMS</span>
                </label>
                <div class="row g-2">
                    <div class="col-12 col-sm-6">
                        <label for="settings-rescue-delay" class="form-label">Delay (seconds)</label>
                        <input
                            id="settings-rescue-delay"
                            type="number"
                            min="10"
                            max="3600"
                            name="telephony_call_flow[missed_call_rescue_delay_seconds]"
                            value="{{ old('telephony_call_flow.missed_call_rescue_delay_seconds', $callFlowConfig['missed_call_rescue_delay_seconds'] ?? 120) }}"
                            class="form-control"
                        >
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="settings-rescue-cooldown" class="form-label">Cooldown (minutes)</label>
                        <input
                            id="settings-rescue-cooldown"
                            type="number"
                            min="30"
                            max="4320"
                            name="telephony_call_flow[missed_call_rescue_cooldown_minutes]"
                            value="{{ old('telephony_call_flow.missed_call_rescue_cooldown_minutes', $callFlowConfig['missed_call_rescue_cooldown_minutes'] ?? 60) }}"
                            class="form-control"
                        >
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-12 col-sm-6">
                        <label for="settings-rescue-text-open" class="form-label">TextBack template (open)</label>
                        <textarea
                            id="settings-rescue-text-open"
                            name="telephony_call_flow[missed_call_rescue_text_open]"
                            rows="3"
                            class="form-control"
                            placeholder="Hey! This is @{{business.name}}. Sorry we missed your call…"
                        >{{ old('telephony_call_flow.missed_call_rescue_text_open', $callFlowConfig['missed_call_rescue_text_open'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="settings-rescue-text-closed" class="form-label">TextBack template (closed)</label>
                        <textarea
                            id="settings-rescue-text-closed"
                            name="telephony_call_flow[missed_call_rescue_text_closed]"
                            rows="3"
                            class="form-control"
                            placeholder="Hey! This is @{{business.name}}. Sorry we missed your call - we're currently closed…"
                        >{{ old('telephony_call_flow.missed_call_rescue_text_closed', $callFlowConfig['missed_call_rescue_text_closed'] ?? '') }}</textarea>
                    </div>
                </div>
                <p class="text-secondary small mb-0">Tokens: <code>@{{business.name}}</code> <code>@{{caller.number}}</code>. Empty templates use shop defaults. Skips opted-out customers and cooldown duplicates.</p>
            </div>

            <div>
                <div class="subheader mb-2">Weekly hours</div>
                <div class="list-group">
                    @foreach ($weekdayLabels as $dayKey => $dayLabel)
                        @php $dayHours = $callFlowConfig['weekly_hours'][$dayKey] ?? ['enabled' => false, 'open' => '09:00', 'close' => '18:00']; @endphp
                        <div class="list-group-item py-2">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-sm-3">
                                    <label class="form-check mb-0">
                                        <input type="hidden" name="telephony_call_flow[weekly_hours][{{ $dayKey }}][enabled]" value="0">
                                        <input
                                            type="checkbox"
                                            name="telephony_call_flow[weekly_hours][{{ $dayKey }}][enabled]"
                                            value="1"
                                            @checked(old("telephony_call_flow.weekly_hours.{$dayKey}.enabled", $dayHours['enabled']))
                                            class="form-check-input"
                                        >
                                        <span class="form-check-label fw-medium">{{ $dayLabel }}</span>
                                    </label>
                                </div>
                                <div class="col-sm-2 d-none d-sm-block small text-secondary">Open hours</div>
                                <div class="col-6 col-sm-3">
                                    <input
                                        type="time"
                                        step="60"
                                        name="telephony_call_flow[weekly_hours][{{ $dayKey }}][open]"
                                        value="{{ old("telephony_call_flow.weekly_hours.{$dayKey}.open", $dayHours['open']) }}"
                                        class="form-control"
                                        aria-label="{{ $dayLabel }} open"
                                    >
                                </div>
                                <div class="col-6 col-sm-3">
                                    <input
                                        type="time"
                                        step="60"
                                        name="telephony_call_flow[weekly_hours][{{ $dayKey }}][close]"
                                        value="{{ old("telephony_call_flow.weekly_hours.{$dayKey}.close", $dayHours['close']) }}"
                                        class="form-control"
                                        aria-label="{{ $dayLabel }} close"
                                    >
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <label for="settings-closed-dates" class="form-label">Holiday closures (one YYYY-MM-DD per line)</label>
                <textarea
                    id="settings-closed-dates"
                    name="telephony_call_flow[closed_dates]"
                    rows="3"
                    class="form-control"
                    placeholder="2026-12-25&#10;2026-11-27"
                >{{ old('telephony_call_flow.closed_dates', implode("\n", $callFlowConfig['closed_dates'])) }}</textarea>
            </div>

            <div>
                <label for="settings-hours-bypass" class="form-label">After-hours test numbers</label>
                <textarea
                    id="settings-hours-bypass"
                    name="telephony_call_flow[hours_bypass_numbers]"
                    rows="3"
                    class="form-control"
                    placeholder="7195551234&#10;(719) 555-9876"
                >{{ old('telephony_call_flow.hours_bypass_numbers', implode("\n", array_map(
                    fn (string $number) => \App\Ark\Operations\PhoneNumber::display($number) ?? $number,
                    $callFlowConfig['hours_bypass_numbers'] ?? [],
                ))) }}</textarea>
                <div class="form-hint">
                    One phone number per line. These callers ring through when the shop is closed - for testing only. Everyone else still goes to voicemail.
                </div>
            </div>
        </div>
        @endif

        @if ($communicationsTab === 'recording')
        <div class="border rounded p-3 d-flex flex-column gap-3">
            <div class="border-bottom pb-2">
                <div class="subheader">Recording and greetings</div>
                <p class="text-secondary small mb-0 mt-1">Call recording disclaimers and voicemail greetings for open and closed hours.</p>
            </div>
            <div class="row g-2">
                <div class="col-12 col-sm-6">
                    <label class="form-check mb-0">
                        <input type="hidden" name="telephony_call_flow[record_inbound_calls]" value="0">
                        <input
                            type="checkbox"
                            name="telephony_call_flow[record_inbound_calls]"
                            value="1"
                            @checked(old('telephony_call_flow.record_inbound_calls', $callFlowConfig['record_inbound_calls'] ?? false))
                            class="form-check-input"
                        >
                        <span class="form-check-label">Record inbound shop calls</span>
                    </label>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-check mb-0">
                        <input type="hidden" name="telephony_call_flow[record_outbound_calls]" value="0">
                        <input
                            type="checkbox"
                            name="telephony_call_flow[record_outbound_calls]"
                            value="1"
                            @checked(old('telephony_call_flow.record_outbound_calls', $callFlowConfig['record_outbound_calls'] ?? false))
                            class="form-check-input"
                        >
                        <span class="form-check-label">Record outbound desk phone calls</span>
                    </label>
                </div>
            </div>

            <div>
                <label for="settings-recording-disclaimer" class="form-label">Recording disclaimer</label>
                <textarea
                    id="settings-recording-disclaimer"
                    name="telephony_call_flow[recording_disclaimer]"
                    rows="2"
                    class="form-control"
                >{{ old('telephony_call_flow.recording_disclaimer', $callFlowConfig['recording_disclaimer']) }}</textarea>
                <div class="form-hint">Played to callers before connect when inbound or outbound recording is enabled.</div>
            </div>

            <div class="row g-2">
                <div class="col-12 col-lg-6">
                    <label for="settings-voicemail-greeting" class="form-label">Open-hours voicemail greeting</label>
                    <textarea
                        id="settings-voicemail-greeting"
                        name="telephony_call_flow[voicemail_greeting]"
                        rows="2"
                        class="form-control"
                    >{{ old('telephony_call_flow.voicemail_greeting', $callFlowConfig['voicemail_greeting']) }}</textarea>
                </div>
                <div class="col-12 col-lg-6">
                    <label for="settings-closed-greeting" class="form-label">Closed-hours greeting</label>
                    <textarea
                        id="settings-closed-greeting"
                        name="telephony_call_flow[closed_greeting]"
                        rows="2"
                        class="form-control"
                    >{{ old('telephony_call_flow.closed_greeting', $callFlowConfig['closed_greeting']) }}</textarea>
                </div>
            </div>
        </div>

        @include('operations.settings.partials.call-intelligence-settings', ['settings' => $settings])
        @endif

        @if ($communicationsTab === 'ring')
        <div class="d-flex flex-column gap-3">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 border-bottom pb-3">
                <div class="flex-fill" style="max-width: 40rem">
                    <h3 class="mb-1">Call routing</h3>
                    <p class="text-secondary small mb-0">
                        Inbound calls ring enabled targets. Use delay to stagger desk before cell. Presence minutes apply per cell on the <span class="fw-medium">When present</span> rule.
                    </p>
                    <div class="mt-3">
                        <label for="settings-cell-whisper" class="form-label">Cell whisper</label>
                        <input
                            id="settings-cell-whisper"
                            type="text"
                            name="telephony_call_flow[cell_whisper_prompt]"
                            maxlength="120"
                            placeholder="Call for {{ $settings->shop_name ?: 'your shop' }}"
                            value="{{ old('telephony_call_flow.cell_whisper_prompt', $callFlowConfig['cell_whisper_prompt'] ?? '') }}"
                            class="form-control"
                        >
                        <div class="form-hint">Short phrase played when a cell endpoint rings before the advisor presses 1. Caller ID shows on the phone - leave blank to use "Call for {{ $settings->shop_name ?: 'shop name' }}". "Press 1 to accept" is added automatically.</div>
                    </div>
                    <fieldset class="mt-3">
                        <legend class="form-label">Caller ringback</legend>
                        <p class="text-secondary small mb-2">
                            What inbound callers hear after the disclaimer while advisors ring - through cell screening and until someone answers.
                        </p>
                        <label class="form-check">
                            <input
                                type="radio"
                                name="telephony_call_flow[caller_ring_audio_mode]"
                                value="standard"
                                class="form-check-input"
                                x-model="callerRingMode"
                            >
                            <span class="form-check-label">
                                <span class="fw-medium">Standard ringback</span>
                                <span class="d-block small text-secondary">US-style ringing while the shop connects the call.</span>
                            </span>
                        </label>
                        <label class="form-check">
                            <input
                                type="radio"
                                name="telephony_call_flow[caller_ring_audio_mode]"
                                value="promo"
                                class="form-check-input"
                                x-model="callerRingMode"
                            >
                            <span class="form-check-label">
                                <span class="fw-medium">Shop promo audio</span>
                                <span class="d-block small text-secondary">Loop a hosted MP3 or WAV - specials, hours, or a short hold message. Must be a public HTTPS URL.</span>
                            </span>
                        </label>
                        <div x-show="callerRingMode === 'promo'" x-cloak class="mt-2">
                            <input
                                type="url"
                                name="telephony_call_flow[caller_ring_promo_url]"
                                x-model="callerRingPromoUrl"
                                placeholder="https://yoursite.com/audio/summer-special.mp3"
                                aria-label="Promo audio URL"
                                @class(['form-control font-monospace small', 'is-invalid' => $errors->has('telephony_call_flow.caller_ring_promo_url')])
                            >
                            @error('telephony_call_flow.caller_ring_promo_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-hint">Tip: upload to your website, S3, or Twilio Assets. Keep clips short - they loop until an advisor answers.</div>
                        </div>
                    </fieldset>
                </div>
                <div>
                    <button
                        type="button"
                        @click="addEndpoint()"
                        class="btn btn-sm"
                    >
                        Add ring target
                    </button>
                </div>
            </div>

            <template x-if="endpoints.length === 0">
                <div class="alert alert-secondary small mb-0">
                    No ring targets yet. Add a cell or desk phone target.
                </div>
            </template>

            <div x-show="endpoints.length > 0" class="table-responsive border rounded">
                <table class="table table-sm table-vcenter mb-0" style="min-width: 56rem">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th style="width: 6.5rem">Type</th>
                            <th>Target</th>
                            <th>Rule / owner</th>
                            <th style="width: 5rem" title="Minutes after last ARK activity before this cell endpoint stops ringing">Presence</th>
                            <th style="width: 5rem">Delay</th>
                            <th style="width: 3rem">On</th>
                            <th style="width: 4.5rem"><span class="visually-hidden">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(endpoint, index) in endpoints" :key="index">
                            <tr>
                                <td>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        :name="`endpoints[${index}][name]`"
                                        x-model="endpoint.name"
                                        placeholder="Desk 1"
                                        aria-label="Name"
                                    >
                                </td>
                                <td>
                                    <select
                                        class="form-select form-select-sm"
                                        :name="`endpoints[${index}][type]`"
                                        x-model="endpoint.type"
                                        aria-label="Type"
                                    >
                                        @foreach ($telephonyEndpointTypes as $endpointType)
                                            @continue($endpointType === \App\Ark\Operations\Telephony\TelephonyEndpointType::MobileApp)
                                            <option value="{{ $endpointType->value }}">{{ $endpointType->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <template x-if="endpoint.type === 'cell'">
                                        <div>
                                            <select
                                                class="form-select form-select-sm"
                                                :class="{ 'is-invalid': endpoint.user_id && ! staffHasPhone(endpoint.user_id) }"
                                                :name="`endpoints[${index}][user_id]`"
                                                x-model="endpoint.user_id"
                                                @change="syncCellEndpoint(endpoint)"
                                                required
                                                aria-label="Staff"
                                            >
                                                <option value="">Staff…</option>
                                                @foreach ($staff as $member)
                                                    <option value="{{ $member->id }}">{{ $member->name }}@if ($member->display_phone) · {{ $member->display_phone }}@elseif (! $member->phone) · no cell @endif</option>
                                                @endforeach
                                            </select>
                                            <div
                                                class="small text-danger fw-medium mt-1"
                                                x-show="endpointError(index, 'user_id')"
                                                x-text="endpointError(index, 'user_id')"
                                                x-cloak
                                            ></div>
                                        </div>
                                    </template>
                                    <template x-if="endpoint.type === 'sip'">
                                        <input
                                            type="text"
                                            class="form-control form-control-sm font-monospace"
                                            :name="`endpoints[${index}][destination]`"
                                            x-model="endpoint.destination"
                                            placeholder="101@domain"
                                            required
                                            aria-label="SIP address"
                                        >
                                    </template>
                                </td>
                                <td>
                                    <template x-if="endpoint.type === 'cell'">
                                        <select
                                            class="form-select form-select-sm"
                                            :name="`endpoints[${index}][ring_schedule]`"
                                            x-model="endpoint.ring_schedule"
                                            aria-label="Ring rule"
                                        >
                                            @foreach ($ringSchedules as $ringSchedule)
                                                <option value="{{ $ringSchedule->value }}">{{ $ringSchedule->label() }}</option>
                                            @endforeach
                                        </select>
                                    </template>
                                    <template x-if="endpoint.type === 'sip'">
                                        <select
                                            class="form-select form-select-sm"
                                            :name="`endpoints[${index}][user_id]`"
                                            x-model="endpoint.user_id"
                                            aria-label="Owner"
                                        >
                                            <option value="">Owner…</option>
                                            @foreach ($staff as $member)
                                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                                            @endforeach
                                        </select>
                                    </template>
                                    <template x-if="endpoint.type === 'sip'">
                                        <input type="hidden" :name="`endpoints[${index}][ring_schedule]`" value="always">
                                    </template>
                                </td>
                                <td>
                                    <template x-if="endpoint.type === 'cell'">
                                        <input
                                            type="number"
                                            min="5"
                                            max="240"
                                            class="form-control form-control-sm"
                                            :name="`endpoints[${index}][presence_timeout_minutes]`"
                                            x-model.number="endpoint.presence_timeout_minutes"
                                            title="Minutes after last ARK activity for When present rule"
                                            aria-label="Presence minutes"
                                        >
                                    </template>
                                    <template x-if="endpoint.type === 'sip'">
                                        <input type="hidden" :name="`endpoints[${index}][presence_timeout_minutes]`" value="30">
                                    </template>
                                    <template x-if="endpoint.type === 'sip'">
                                        <span class="text-secondary small">-</span>
                                    </template>
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        min="0"
                                        max="60"
                                        class="form-control form-control-sm"
                                        :name="`endpoints[${index}][ring_delay_seconds]`"
                                        x-model.number="endpoint.ring_delay_seconds"
                                        title="Ring delay in seconds"
                                        aria-label="Ring delay in seconds"
                                    >
                                </td>
                                <td class="text-center">
                                    <input type="hidden" :name="`endpoints[${index}][enabled]`" value="0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :name="`endpoints[${index}][enabled]`"
                                        value="1"
                                        x-model="endpoint.enabled"
                                        aria-label="Enabled"
                                    >
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        @click="removeEndpoint(index)"
                                        class="btn btn-sm btn-ghost-danger"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="d-flex justify-content-end border-top pt-3">
            <button type="submit" class="btn btn-primary">
                Save telephony settings
            </button>
        </div>
    </form>
    @endif
</div>
