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
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900',
        'muted' => 'border-slate-200 bg-slate-50 text-slate-700',
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
@endphp

<section x-show="active === 'communications'" x-cloak>
    <div class="space-y-3 border border-slate-300 bg-white p-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Communications</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Phone, SMS, and email</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Business phone, SMS, email, Messenger, and call routing.
            </p>
        </div>

        <div class="grid gap-px border border-slate-300 bg-slate-300 text-sm sm:grid-cols-4 lg:grid-cols-8">
            @foreach ([
                'general' => 'General',
                'email' => 'Email',
                'messenger' => 'Messenger',
                'hours' => 'Hours',
                'recording' => 'Recording',
                'ring' => 'Call routing',
                'mobile' => 'Mobile',
            ] as $tabKey => $tabLabel)
                <a
                    href="{{ route('operations.settings.shop.edit', ['section' => 'communications', 'communications-tab' => $tabKey]) }}"
                    @class([
                        'px-3 py-2 text-left font-semibold',
                        $communicationsTab === $tabKey
                            ? 'bg-slate-950 text-white'
                            : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950',
                    ])
                >{{ $tabLabel }}</a>
            @endforeach
        </div>

        @if ($communicationsTab === 'email')
            @include('operations.settings.partials.customer-email-settings', ['settings' => $settings])
        @elseif ($communicationsTab === 'messenger')
            @include('operations.settings.partials.communications-channels-settings', ['settings' => $settings])
        @elseif ($communicationsTab === 'mobile')
            @include('operations.settings.partials.mobile-push-settings', [
                'settings' => $settings,
                'telephonyHealth' => $telephonyHealth,
            ])
        @else
        <form
            method="POST"
            action="{{ route('operations.settings.shop.telephony.update') }}"
            class="space-y-3"
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
            <div class="space-y-3">
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

                @include('operations.settings.partials.message-actions-settings', ['settings' => $settings])

                <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
                    <div class="border-b border-slate-200 pb-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Messaging &amp; voice transport</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Outbound SMS and voice calling require a messaging/voice transport implementation. Stock ARK Core does not ship with one configured.
                        </p>
                    </div>

                    <label class="block max-w-md">
                        <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Business number</span>
                        <input
                            type="text"
                            name="telephony_inbound_number"
                            value="{{ old('telephony_inbound_number', $settings->telephony_inbound_number) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            placeholder="+1 (719) 555-0100"
                        >
                        <span class="mt-1 block text-[11px] leading-4 text-slate-500">The number customers call and see on outbound shop calls.</span>
                    </label>
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
                    class="space-y-2 rounded-sm border border-slate-200 bg-slate-50/60 p-3"
                >
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Operational test</p>
                        <h3 class="mt-1 text-sm font-black text-slate-950">Test incoming call</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Fires the incoming-call banner without placing a real phone call.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-end gap-2">
                        <label class="min-w-[12rem] flex-1">
                            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Caller phone</span>
                            <input
                                type="text"
                                x-model="phone"
                                class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                                placeholder="7195551234"
                            >
                        </label>

                        <button
                            type="button"
                            @click="testIncomingCall()"
                            :disabled="testing"
                            class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-60"
                        >
                            <span x-text="testing ? 'Testing…' : 'Test incoming call'"></span>
                        </button>
                    </div>

                    <p
                        x-show="testMessage"
                        x-text="testMessage"
                        class="rounded-sm border px-3 py-2 text-xs font-semibold"
                        :class="{
                            'border-emerald-200 bg-emerald-50 text-emerald-900': testTone === 'success',
                            'border-rose-200 bg-rose-50 text-rose-900': testTone === 'danger',
                            'border-slate-200 bg-slate-50 text-slate-700': testTone === 'muted',
                        }"
                    ></p>
                </div>
            </div>
            @endif

            @if ($communicationsTab === 'hours')
            <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
                <div class="border-b border-slate-200 pb-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Call hours</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Open hours control when inbound calls ring the shop. Closed hours go to voicemail.</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-3">
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Timezone</span>
                        <input
                            type="text"
                            name="shop_timezone"
                            value="{{ old('shop_timezone', $settings->shop_timezone) }}"
                            required
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Ring timeout (seconds)</span>
                        <input
                            type="number"
                            min="10"
                            max="60"
                            name="telephony_call_flow[dial_timeout_seconds]"
                            value="{{ old('telephony_call_flow.dial_timeout_seconds', $callFlowConfig['dial_timeout_seconds']) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Owned popup dismiss (seconds)</span>
                        <input
                            type="number"
                            min="3"
                            max="60"
                            name="telephony_call_flow[owned_popup_timeout_seconds]"
                            value="{{ old('telephony_call_flow.owned_popup_timeout_seconds', $callFlowConfig['owned_popup_timeout_seconds']) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >
                    </label>
                </div>
                <p class="text-xs text-slate-500">When another advisor owns the call, other popups auto-dismiss after this many seconds.</p>

                <div class="rounded-sm border border-slate-200 bg-slate-50 p-3 space-y-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Advisor comms accountability</p>
                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="hidden" name="telephony_call_flow[comms_attention_gate_enabled]" value="0">
                        <input
                            type="checkbox"
                            name="telephony_call_flow[comms_attention_gate_enabled]"
                            value="1"
                            @checked(old('telephony_call_flow.comms_attention_gate_enabled', $callFlowConfig['comms_attention_gate_enabled'] ?? true))
                            class="mt-0.5 rounded border-slate-300"
                        >
                        <span>Block other ARK pages until the Work communications queue is cleared (Mark Handled / Mark Read / Reply). Incoming call and text popups still appear either way.</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="hidden" name="telephony_call_flow[comms_escalation_enabled]" value="0">
                        <input
                            type="checkbox"
                            name="telephony_call_flow[comms_escalation_enabled]"
                            value="1"
                            @checked(old('telephony_call_flow.comms_escalation_enabled', $callFlowConfig['comms_escalation_enabled'] ?? true))
                            class="mt-0.5 rounded border-slate-300"
                        >
                        <span>Text all active advisors when calls, texts, or website leads stay unhandled (requires advisor phone on staff profile).</span>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Escalation delay (minutes)</span>
                            <input
                                type="number"
                                min="1"
                                max="30"
                                name="telephony_call_flow[comms_escalation_delay_minutes]"
                                value="{{ old('telephony_call_flow.comms_escalation_delay_minutes', $callFlowConfig['comms_escalation_delay_minutes'] ?? 3) }}"
                                class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            >
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Escalation cooldown (minutes)</span>
                            <input
                                type="number"
                                min="5"
                                max="240"
                                name="telephony_call_flow[comms_escalation_cooldown_minutes]"
                                value="{{ old('telephony_call_flow.comms_escalation_cooldown_minutes', $callFlowConfig['comms_escalation_cooldown_minutes'] ?? 30) }}"
                                class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            >
                        </label>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="hidden" name="telephony_call_flow[comms_browser_notifications_enabled]" value="0">
                        <input
                            type="checkbox"
                            name="telephony_call_flow[comms_browser_notifications_enabled]"
                            value="1"
                            @checked(old('telephony_call_flow.comms_browser_notifications_enabled', $callFlowConfig['comms_browser_notifications_enabled'] ?? true))
                            class="mt-0.5 rounded border-slate-300"
                        >
                        <span>Browser notifications + chime when ARK tab is open (advisor must allow notifications once).</span>
                    </label>
                </div>

                <div class="rounded-sm border border-slate-200 bg-slate-50 p-3 space-y-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Missed Call Rescue</p>
                    <p class="text-xs text-slate-500">Automatically text callers after a missed inbound call. Uses Conversation (system SMS) — not a separate inbox.</p>
                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="hidden" name="telephony_call_flow[missed_call_rescue_enabled]" value="0">
                        <input
                            type="checkbox"
                            name="telephony_call_flow[missed_call_rescue_enabled]"
                            value="1"
                            @checked(old('telephony_call_flow.missed_call_rescue_enabled', $callFlowConfig['missed_call_rescue_enabled'] ?? false))
                            class="mt-0.5 rounded border-slate-300"
                        >
                        <span>Enable missed-call SMS</span>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Delay (seconds)</span>
                            <input
                                type="number"
                                min="10"
                                max="3600"
                                name="telephony_call_flow[missed_call_rescue_delay_seconds]"
                                value="{{ old('telephony_call_flow.missed_call_rescue_delay_seconds', $callFlowConfig['missed_call_rescue_delay_seconds'] ?? 120) }}"
                                class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            >
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Cooldown (minutes)</span>
                            <input
                                type="number"
                                min="30"
                                max="4320"
                                name="telephony_call_flow[missed_call_rescue_cooldown_minutes]"
                                value="{{ old('telephony_call_flow.missed_call_rescue_cooldown_minutes', $callFlowConfig['missed_call_rescue_cooldown_minutes'] ?? 60) }}"
                                class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            >
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">TextBack template (open)</span>
                            <textarea
                                name="telephony_call_flow[missed_call_rescue_text_open]"
                                rows="3"
                                class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                                placeholder="Hey! This is @{{business.name}}. Sorry we missed your call…"
                            >{{ old('telephony_call_flow.missed_call_rescue_text_open', $callFlowConfig['missed_call_rescue_text_open'] ?? '') }}</textarea>
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">TextBack template (closed)</span>
                            <textarea
                                name="telephony_call_flow[missed_call_rescue_text_closed]"
                                rows="3"
                                class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                                placeholder="Hey! This is @{{business.name}}. Sorry we missed your call — we're currently closed…"
                            >{{ old('telephony_call_flow.missed_call_rescue_text_closed', $callFlowConfig['missed_call_rescue_text_closed'] ?? '') }}</textarea>
                        </label>
                    </div>
                    <p class="text-xs text-slate-500">Tokens: <code class="text-[11px]">@{{business.name}}</code> <code class="text-[11px]">@{{caller.number}}</code>. Empty templates use shop defaults. Skips opted-out customers and cooldown duplicates.</p>
                </div>

                <div class="space-y-1">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Weekly hours</p>
                    @foreach ($weekdayLabels as $dayKey => $dayLabel)
                        @php $dayHours = $callFlowConfig['weekly_hours'][$dayKey] ?? ['enabled' => false, 'open' => '09:00', 'close' => '18:00']; @endphp
                        <div class="grid gap-2 rounded-sm border border-slate-200 bg-white px-2 py-2 sm:grid-cols-[7rem_5rem_1fr_1fr] sm:items-center">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="hidden" name="telephony_call_flow[weekly_hours][{{ $dayKey }}][enabled]" value="0">
                                <input
                                    type="checkbox"
                                    name="telephony_call_flow[weekly_hours][{{ $dayKey }}][enabled]"
                                    value="1"
                                    @checked(old("telephony_call_flow.weekly_hours.{$dayKey}.enabled", $dayHours['enabled']))
                                >
                                {{ $dayLabel }}
                            </label>
                            <span class="hidden text-[10px] font-bold uppercase tracking-wide text-slate-400 sm:block">Open hours</span>
                            <input
                                type="time"
                                name="telephony_call_flow[weekly_hours][{{ $dayKey }}][open]"
                                value="{{ old("telephony_call_flow.weekly_hours.{$dayKey}.open", $dayHours['open']) }}"
                                class="h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            >
                            <input
                                type="time"
                                name="telephony_call_flow[weekly_hours][{{ $dayKey }}][close]"
                                value="{{ old("telephony_call_flow.weekly_hours.{$dayKey}.close", $dayHours['close']) }}"
                                class="h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            >
                        </div>
                    @endforeach
                </div>

                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Holiday closures (one YYYY-MM-DD per line)</span>
                    <textarea
                        name="telephony_call_flow[closed_dates]"
                        rows="3"
                        class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        placeholder="2026-12-25&#10;2026-11-27"
                    >{{ old('telephony_call_flow.closed_dates', implode("\n", $callFlowConfig['closed_dates'])) }}</textarea>
                </label>

                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">After-hours test numbers</span>
                    <textarea
                        name="telephony_call_flow[hours_bypass_numbers]"
                        rows="3"
                        class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        placeholder="7195551234&#10;(719) 555-9876"
                    >{{ old('telephony_call_flow.hours_bypass_numbers', implode("\n", array_map(
                        fn (string $number) => \App\Ark\Operations\PhoneNumber::display($number) ?? $number,
                        $callFlowConfig['hours_bypass_numbers'] ?? [],
                    ))) }}</textarea>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        One phone number per line. These callers ring through when the shop is closed — for testing only. Everyone else still goes to voicemail.
                    </p>
                </label>
            </div>
            @endif

            @if ($communicationsTab === 'recording')
            <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
                <div class="border-b border-slate-200 pb-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Recording and greetings</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Call recording disclaimers and voicemail greetings for open and closed hours.</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="hidden" name="telephony_call_flow[record_inbound_calls]" value="0">
                        <input
                            type="checkbox"
                            name="telephony_call_flow[record_inbound_calls]"
                            value="1"
                            @checked(old('telephony_call_flow.record_inbound_calls', $callFlowConfig['record_inbound_calls'] ?? false))
                        >
                        Record inbound shop calls
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="hidden" name="telephony_call_flow[record_outbound_calls]" value="0">
                        <input
                            type="checkbox"
                            name="telephony_call_flow[record_outbound_calls]"
                            value="1"
                            @checked(old('telephony_call_flow.record_outbound_calls', $callFlowConfig['record_outbound_calls'] ?? false))
                        >
                        Record outbound desk phone calls
                    </label>
                </div>

                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Recording disclaimer</span>
                    <textarea
                        name="telephony_call_flow[recording_disclaimer]"
                        rows="2"
                        class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                    >{{ old('telephony_call_flow.recording_disclaimer', $callFlowConfig['recording_disclaimer']) }}</textarea>
                    <span class="mt-1 block text-[11px] leading-4 text-slate-500">Played to callers before connect when inbound or outbound recording is enabled.</span>
                </label>

                <div class="grid gap-2 lg:grid-cols-2">
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Open-hours voicemail greeting</span>
                        <textarea
                            name="telephony_call_flow[voicemail_greeting]"
                            rows="2"
                            class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >{{ old('telephony_call_flow.voicemail_greeting', $callFlowConfig['voicemail_greeting']) }}</textarea>
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Closed-hours greeting</span>
                        <textarea
                            name="telephony_call_flow[closed_greeting]"
                            rows="2"
                            class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >{{ old('telephony_call_flow.closed_greeting', $callFlowConfig['closed_greeting']) }}</textarea>
                    </label>
                </div>
            </div>

            @include('operations.settings.partials.call-intelligence-settings', ['settings' => $settings])
            @endif

            @if ($communicationsTab === 'ring')
            @php
                $ringGridCols = 'grid-cols-[minmax(6rem,0.9fr)_5.5rem_minmax(9rem,1.3fr)_minmax(10rem,1.1fr)_3.5rem_3.5rem_2.5rem_3rem]';
                $ringField = 'min-h-9 w-full min-w-0 rounded-sm border border-slate-300 bg-white px-2 py-1.5 text-sm leading-normal text-slate-800';
                $ringFieldMono = 'min-h-9 w-full min-w-0 rounded-sm border border-slate-300 bg-white px-2 py-1.5 font-mono text-[11px] leading-normal text-slate-800';
            @endphp
            <div class="space-y-3">
                <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2 border-b border-slate-200 pb-2">
                    <div class="min-w-0">
                        <h3 class="text-sm font-black text-slate-950">Call routing</h3>
                        <p class="mt-0.5 text-[11px] leading-4 text-slate-500">
                            Inbound calls ring enabled targets. Use delay to stagger desk before cell. Presence minutes apply per cell on the <span class="font-semibold text-slate-600">When present</span> rule.
                        </p>
                        <label class="mt-2 block max-w-xl">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Cell whisper</span>
                            <input
                                type="text"
                                name="telephony_call_flow[cell_whisper_prompt]"
                                maxlength="120"
                                placeholder="Call for {{ $settings->shop_name ?: 'your shop' }}"
                                value="{{ old('telephony_call_flow.cell_whisper_prompt', $callFlowConfig['cell_whisper_prompt'] ?? '') }}"
                                class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            >
                            <span class="mt-1 block text-[11px] leading-4 text-slate-500">Short phrase played when a cell endpoint rings before the advisor presses 1. Caller ID shows on the phone — leave blank to use "Call for {{ $settings->shop_name ?: 'shop name' }}". "Press 1 to accept" is added automatically.</span>
                        </label>
                        <fieldset class="mt-3 max-w-xl space-y-2">
                            <legend class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Caller ringback</legend>
                            <p class="text-[11px] leading-4 text-slate-500">
                                What inbound callers hear after the disclaimer while advisors ring — through cell screening and until someone answers.
                            </p>
                            <div class="mt-2 space-y-2">
                                <label class="flex items-start gap-2 text-sm text-slate-800">
                                    <input
                                        type="radio"
                                        name="telephony_call_flow[caller_ring_audio_mode]"
                                        value="standard"
                                        class="mt-0.5 rounded-full border-slate-300"
                                        x-model="callerRingMode"
                                    >
                                    <span>
                                        <span class="font-semibold text-slate-900">Standard ringback</span>
                                        <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">US-style ringing while the shop connects the call.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-2 text-sm text-slate-800">
                                    <input
                                        type="radio"
                                        name="telephony_call_flow[caller_ring_audio_mode]"
                                        value="promo"
                                        class="mt-0.5 rounded-full border-slate-300"
                                        x-model="callerRingMode"
                                    >
                                    <span>
                                        <span class="font-semibold text-slate-900">Shop promo audio</span>
                                        <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Loop a hosted MP3 or WAV — specials, hours, or a short hold message. Must be a public HTTPS URL.</span>
                                    </span>
                                </label>
                            </div>
                            <div x-show="callerRingMode === 'promo'" x-cloak class="mt-2">
                                <input
                                    type="url"
                                    name="telephony_call_flow[caller_ring_promo_url]"
                                    x-model="callerRingPromoUrl"
                                    placeholder="https://yoursite.com/audio/summer-special.mp3"
                                    class="w-full rounded-sm border-slate-300 font-mono text-[11px] text-slate-800"
                                >
                                @error('telephony_call_flow.caller_ring_promo_url')
                                    <p class="mt-1 text-[11px] text-rose-700">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-[11px] leading-4 text-slate-500">Tip: upload to your website, S3, or a public media host. Keep clips short — they loop until an advisor answers.</p>
                            </div>
                        </fieldset>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-end gap-2">
                        <button
                            type="button"
                            @click="addEndpoint()"
                            class="inline-flex h-8 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                        >
                            Add ring target
                        </button>
                    </div>
                </div>

                <template x-if="endpoints.length === 0">
                    <p class="rounded-sm border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        No ring targets yet. Add a cell or desk phone target.
                    </p>
                </template>

                <div x-show="endpoints.length > 0" class="overflow-x-auto rounded-sm border border-slate-200">
                    <div class="min-w-[56rem]">
                        <div class="grid {{ $ringGridCols }} gap-x-2 border-b border-slate-200 bg-slate-50 px-2 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            <span>Name</span>
                            <span>Type</span>
                            <span>Target</span>
                            <span>Rule / owner</span>
                            <span title="Minutes after last ARK activity before this cell endpoint stops ringing">Presence</span>
                            <span>Delay</span>
                            <span>On</span>
                            <span></span>
                        </div>

                        <template x-for="(endpoint, index) in endpoints" :key="index">
                            <div class="border-b border-slate-100 last:border-b-0">
                                <div class="grid {{ $ringGridCols }} items-center gap-x-2 px-2 py-1.5">
                                    <input
                                        type="text"
                                        class="{{ $ringField }}"
                                        :name="`endpoints[${index}][name]`"
                                        x-model="endpoint.name"
                                        placeholder="Desk 1"
                                    >

                                    <select
                                        class="{{ $ringField }}"
                                        :name="`endpoints[${index}][type]`"
                                        x-model="endpoint.type"
                                    >
                                        @foreach ($telephonyEndpointTypes as $endpointType)
                                            @continue($endpointType === \App\Ark\Operations\Telephony\TelephonyEndpointType::MobileApp)
                                            <option value="{{ $endpointType->value }}">{{ $endpointType->label() }}</option>
                                        @endforeach
                                    </select>

                                    <div class="min-w-0">
                                        <template x-if="endpoint.type === 'cell'">
                                            <div>
                                                <select
                                                    class="{{ $ringField }}"
                                                    :class="{ 'border-amber-400': endpoint.user_id && ! staffHasPhone(endpoint.user_id) }"
                                                    :name="`endpoints[${index}][user_id]`"
                                                    x-model="endpoint.user_id"
                                                    @change="syncCellEndpoint(endpoint)"
                                                    required
                                                >
                                                    <option value="">Staff…</option>
                                                    @foreach ($staff as $member)
                                                        <option value="{{ $member->id }}">{{ $member->name }}@if ($member->display_phone) · {{ $member->display_phone }}@elseif (! $member->phone) · no cell @endif</option>
                                                    @endforeach
                                                </select>
                                                <p
                                                    class="mt-0.5 text-[10px] font-semibold text-rose-700"
                                                    x-show="endpointError(index, 'user_id')"
                                                    x-text="endpointError(index, 'user_id')"
                                                    x-cloak
                                                ></p>
                                            </div>
                                        </template>
                                        <template x-if="endpoint.type === 'sip'">
                                            <input
                                                type="text"
                                                class="{{ $ringFieldMono }}"
                                                :name="`endpoints[${index}][destination]`"
                                                x-model="endpoint.destination"
                                                placeholder="101@domain"
                                                required
                                            >
                                        </template>
                                    </div>

                                    <div class="min-w-0">
                                        <template x-if="endpoint.type === 'cell'">
                                            <select
                                                class="{{ $ringField }}"
                                                :name="`endpoints[${index}][ring_schedule]`"
                                                x-model="endpoint.ring_schedule"
                                            >
                                                @foreach ($ringSchedules as $ringSchedule)
                                                    <option value="{{ $ringSchedule->value }}">{{ $ringSchedule->label() }}</option>
                                                @endforeach
                                            </select>
                                        </template>
                                        <template x-if="endpoint.type === 'sip'">
                                            <select
                                                class="{{ $ringField }}"
                                                :name="`endpoints[${index}][user_id]`"
                                                x-model="endpoint.user_id"
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
                                    </div>

                                    <div class="min-w-0">
                                        <template x-if="endpoint.type === 'cell'">
                                            <input
                                                type="number"
                                                min="5"
                                                max="240"
                                                class="{{ $ringField }}"
                                                :name="`endpoints[${index}][presence_timeout_minutes]`"
                                                x-model.number="endpoint.presence_timeout_minutes"
                                                title="Minutes after last ARK activity for When present rule"
                                            >
                                        </template>
                                        <template x-if="endpoint.type === 'sip'">
                                            <input type="hidden" :name="`endpoints[${index}][presence_timeout_minutes]`" value="30">
                                            <span class="flex min-h-9 items-center text-[11px] font-semibold text-slate-400">—</span>
                                        </template>
                                    </div>

                                    <input
                                        type="number"
                                        min="0"
                                        max="60"
                                        class="{{ $ringField }}"
                                        :name="`endpoints[${index}][ring_delay_seconds]`"
                                        x-model.number="endpoint.ring_delay_seconds"
                                        title="Ring delay in seconds"
                                    >

                                    <label class="flex min-h-9 items-center justify-center">
                                        <input type="hidden" :name="`endpoints[${index}][enabled]`" value="0">
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300"
                                            :name="`endpoints[${index}][enabled]`"
                                            value="1"
                                            x-model="endpoint.enabled"
                                        >
                                    </label>

                                    <button
                                        type="button"
                                        @click="removeEndpoint(index)"
                                        class="flex min-h-9 items-center text-[11px] font-semibold text-slate-500 hover:text-rose-700"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            @endif

            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                Save telephony settings
            </button>
        </form>
        @endif
    </div>
</section>
