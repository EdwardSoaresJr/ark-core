<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Communications\CommunicationsAccentColor;
use App\Ark\Operations\Communications\CommunicationsQuickReplyTemplates;
use App\Ark\Mobile\Push\MobilePushSettings;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Messaging\MessageActionKey;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Mail\ArkMailIdentityClient;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Telephony\TelephonyCallerRingtone;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Telephony\TelephonyEndpointSync;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Telephony\TelephonyRingSchedule;
use App\Ark\Operations\Telephony\TelephonyStaffPhoneForRing;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShopCommunicationsSettingsController
{
    use InteractsWithShopSettingsPersistence;

    public function __construct(
        private readonly ?EstimateDocumentService $estimateDocumentService = null,
        private readonly ?EstimateTotalsCalculator $estimateTotalsCalculator = null,
    ) {}

    protected function estimateDocuments(): EstimateDocumentService
    {
        return $this->estimateDocumentService ?? app(EstimateDocumentService::class);
    }

    protected function totalsCalculator(): EstimateTotalsCalculator
    {
        return $this->estimateTotalsCalculator ?? app(EstimateTotalsCalculator::class);
    }

    public function updateCustomerMessaging(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'google_reviews_url' => ['nullable', 'url', 'max:2048'],
            'postmark_reply_to' => ['nullable', 'email', 'max:255'],
            'postmark_reply_to_name' => ['nullable', 'string', 'max:255'],
            'telephony_call_flow' => ['nullable', 'array'],
            'telephony_call_flow.comms_attention_gate_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.comms_escalation_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.comms_escalation_delay_minutes' => ['nullable', 'integer', 'min:1', 'max:30'],
            'telephony_call_flow.comms_escalation_cooldown_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'telephony_call_flow.comms_browser_notifications_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.missed_call_rescue_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.missed_call_rescue_delay_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'telephony_call_flow.missed_call_rescue_cooldown_minutes' => ['nullable', 'integer', 'min:30', 'max:4320'],
            'telephony_call_flow.missed_call_rescue_text_open' => ['nullable', 'string', 'max:500'],
            'telephony_call_flow.missed_call_rescue_text_closed' => ['nullable', 'string', 'max:500'],
            'message_actions' => ['nullable', 'array'],
            'message_actions.tow_company' => ['nullable', 'string', 'max:120'],
            'message_actions.tow_phone' => ['nullable', 'string', 'max:32'],
            'message_actions.tow_notes' => ['nullable', 'string', 'max:500'],
            'message_actions.wifi_ssid' => ['nullable', 'string', 'max:120'],
            'message_actions.wifi_password' => ['nullable', 'string', 'max:120'],
            'message_actions.after_hours_pickup' => ['nullable', 'string', 'max:500'],
            'message_actions.colors' => ['nullable', 'array'],
            'message_actions.colors.*' => ['nullable', Rule::in(CommunicationsAccentColor::keys())],
        ]);

        $settings = ShopSettings::current();
        $callFlowInput = is_array($data['telephony_call_flow'] ?? null) ? $data['telephony_call_flow'] : [];
        $existingFlow = TelephonyCallFlowSettings::fromShopSettings($settings)->toArray();

        $callFlow = array_merge($existingFlow, [
            'comms_attention_gate_enabled' => $request->boolean(
                'telephony_call_flow.comms_attention_gate_enabled',
                (bool) ($existingFlow['comms_attention_gate_enabled'] ?? true),
            ),
            'comms_escalation_enabled' => $request->boolean(
                'telephony_call_flow.comms_escalation_enabled',
                (bool) ($existingFlow['comms_escalation_enabled'] ?? true),
            ),
            'comms_escalation_delay_minutes' => (int) ($callFlowInput['comms_escalation_delay_minutes'] ?? $existingFlow['comms_escalation_delay_minutes'] ?? 3),
            'comms_escalation_cooldown_minutes' => (int) ($callFlowInput['comms_escalation_cooldown_minutes'] ?? $existingFlow['comms_escalation_cooldown_minutes'] ?? 30),
            'comms_browser_notifications_enabled' => $request->boolean(
                'telephony_call_flow.comms_browser_notifications_enabled',
                (bool) ($existingFlow['comms_browser_notifications_enabled'] ?? true),
            ),
            'missed_call_rescue_enabled' => $request->boolean(
                'telephony_call_flow.missed_call_rescue_enabled',
                (bool) ($existingFlow['missed_call_rescue_enabled'] ?? false),
            ),
            'missed_call_rescue_delay_seconds' => (int) ($callFlowInput['missed_call_rescue_delay_seconds'] ?? $existingFlow['missed_call_rescue_delay_seconds'] ?? 120),
            'missed_call_rescue_cooldown_minutes' => (int) ($callFlowInput['missed_call_rescue_cooldown_minutes'] ?? $existingFlow['missed_call_rescue_cooldown_minutes'] ?? 60),
            'missed_call_rescue_text_open' => array_key_exists('missed_call_rescue_text_open', $callFlowInput)
                ? trim((string) ($callFlowInput['missed_call_rescue_text_open'] ?? ''))
                : (string) ($existingFlow['missed_call_rescue_text_open'] ?? ''),
            'missed_call_rescue_text_closed' => array_key_exists('missed_call_rescue_text_closed', $callFlowInput)
                ? trim((string) ($callFlowInput['missed_call_rescue_text_closed'] ?? ''))
                : (string) ($existingFlow['missed_call_rescue_text_closed'] ?? ''),
        ]);

        $messageActionsInput = is_array($data['message_actions'] ?? null) ? $data['message_actions'] : [];
        $colorInput = is_array($messageActionsInput['colors'] ?? null) ? $messageActionsInput['colors'] : [];
        $colors = [];
        foreach (MessageActionKey::advisorOneTap() as $action) {
            $colors[$action->value] = CommunicationsAccentColor::normalize($colorInput[$action->value] ?? null);
        }

        $settings->persistTrusted([
            'telephony_call_flow' => $callFlow,
            'google_reviews_url' => $this->nullableTrimmedString($data['google_reviews_url'] ?? null),
            'postmark_reply_to' => $this->nullableTrimmedString($data['postmark_reply_to'] ?? null),
            'postmark_reply_to_name' => $this->nullableTrimmedString($data['postmark_reply_to_name'] ?? null),
            'message_actions' => [
                'tow_company' => $this->nullableTrimmedString($messageActionsInput['tow_company'] ?? null),
                'tow_phone' => $this->nullableTrimmedString($messageActionsInput['tow_phone'] ?? null),
                'tow_notes' => $this->nullableTrimmedString($messageActionsInput['tow_notes'] ?? null),
                'wifi_ssid' => $this->nullableTrimmedString($messageActionsInput['wifi_ssid'] ?? null),
                'wifi_password' => $this->nullableTrimmedString($messageActionsInput['wifi_password'] ?? null),
                'after_hours_pickup' => $this->nullableTrimmedString($messageActionsInput['after_hours_pickup'] ?? null),
                'colors' => $colors,
            ],
        ]);

        $redirect = redirect()
            ->route('operations.settings.shop.edit', ['section' => 'customer-messaging'])
            ->with('status', 'Customer messaging settings saved.');

        if (PlatformConnection::current()->isConnected()) {
            $synced = app(ArkMailIdentityClient::class)->syncShopReplyTo();
            if (! $synced) {
                $redirect->with('warning', 'Reply-To was saved here, but ARK Platform could not be updated right now. Try again from Settings → ARK Platform after Cloud is reachable.');
            }
        }

        return $redirect;
    }

    public function updateTelephony(
        Request $request,
        TelephonyEndpointSync $endpointSync,
        TelephonyStaffPhoneForRing $staffPhoneForRing,
    ): RedirectResponse {
        $communicationsTab = in_array($request->input('communications_tab'), ['general', 'email', 'messenger', 'hours', 'recording', 'ring', 'infrastructure', 'mobile'], true)
            ? (string) $request->input('communications_tab')
            : 'general';

        if ($communicationsTab === 'infrastructure') {
            return $this->updateCommunicationsInfrastructure($request);
        }

        if ($communicationsTab === 'mobile') {
            return $this->updateMobilePush($request);
        }

        if ($communicationsTab === 'messenger') {
            return $this->updateCommunicationsChannels($request);
        }

        $this->normalizeWeeklyHoursClocks($request);

        $data = $request->validate([
            'twilio_account_sid' => ['nullable', 'string', 'max:64'],
            'twilio_auth_token' => ['nullable', 'string', 'max:128'],
            'twilio_api_key_sid' => ['nullable', 'string', 'max:64'],
            'twilio_api_key_secret' => ['nullable', 'string', 'max:128'],
            'twilio_voice_twiml_app_sid' => ['nullable', 'string', 'max:64'],
            'twilio_fcm_credential_sid' => ['nullable', 'string', 'max:64'],
            'twilio_apns_voip_credential_sid' => ['nullable', 'string', 'max:64'],
            'telephony_inbound_number' => ['nullable', 'string', 'max:32'],
            'telephony_provider' => ['nullable', Rule::enum(TelephonyProviderType::class)],
            'telephony_call_flow' => ['nullable', 'array'],
            'shop_timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'telephony_call_flow.timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'telephony_call_flow.weekly_hours' => ['nullable', 'array'],
            'telephony_call_flow.weekly_hours.*.enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.weekly_hours.*.open' => ['nullable', 'date_format:H:i'],
            'telephony_call_flow.weekly_hours.*.close' => ['nullable', 'date_format:H:i'],
            'telephony_call_flow.closed_dates' => ['nullable', 'string'],
            'telephony_call_flow.hours_bypass_numbers' => ['nullable', 'string'],
            'telephony_call_flow.voicemail_greeting' => ['nullable', 'string', 'max:500'],
            'telephony_call_flow.closed_greeting' => ['nullable', 'string', 'max:500'],
            'telephony_call_flow.recording_disclaimer' => ['nullable', 'string', 'max:500'],
            'telephony_call_flow.cell_whisper_prompt' => ['nullable', 'string', 'max:120'],
            'telephony_call_flow.caller_ring_audio_mode' => ['nullable', Rule::in([
                TelephonyCallerRingtone::AUDIO_MODE_STANDARD,
                TelephonyCallerRingtone::AUDIO_MODE_PROMO,
            ])],
            'telephony_call_flow.caller_ring_promo_url' => [
                'nullable',
                'string',
                'max:500',
                'url',
                Rule::requiredIf(fn (): bool => $request->input('telephony_call_flow.caller_ring_audio_mode') === TelephonyCallerRingtone::AUDIO_MODE_PROMO),
                Rule::when(
                    $request->input('telephony_call_flow.caller_ring_audio_mode') === TelephonyCallerRingtone::AUDIO_MODE_PROMO,
                    ['starts_with:https://'],
                ),
            ],
            'telephony_call_flow.record_inbound_calls' => ['nullable', 'boolean'],
            'telephony_call_flow.record_outbound_calls' => ['nullable', 'boolean'],
            'telephony_call_flow.dial_timeout_seconds' => ['nullable', 'integer', 'min:10', 'max:60'],
            'telephony_call_flow.owned_popup_timeout_seconds' => ['nullable', 'integer', 'min:3', 'max:60'],
            'telephony_call_flow.presence_timeout_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'telephony_call_flow.comms_attention_gate_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.comms_escalation_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.comms_escalation_delay_minutes' => ['nullable', 'integer', 'min:1', 'max:30'],
            'telephony_call_flow.comms_escalation_cooldown_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'telephony_call_flow.comms_browser_notifications_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.missed_call_rescue_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.missed_call_rescue_delay_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'telephony_call_flow.missed_call_rescue_cooldown_minutes' => ['nullable', 'integer', 'min:30', 'max:4320'],
            'telephony_call_flow.missed_call_rescue_text_open' => ['nullable', 'string', 'max:500'],
            'telephony_call_flow.missed_call_rescue_text_closed' => ['nullable', 'string', 'max:500'],
            'endpoints' => ['nullable', 'array'],
            'endpoints.*.name' => ['required_with:endpoints', 'string', 'max:64'],
            'endpoints.*.type' => ['required_with:endpoints', Rule::enum(TelephonyEndpointType::class)],
            'endpoints.*.destination' => ['nullable', 'string', 'max:255'],
            'endpoints.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'endpoints.*.ring_schedule' => ['nullable', Rule::enum(TelephonyRingSchedule::class)],
            'endpoints.*.ring_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'endpoints.*.presence_timeout_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'endpoints.*.enabled' => ['nullable', 'boolean'],
            'openai_api_key' => ['nullable', 'string', 'max:512'],
            'openai_transcription_model' => ['nullable', 'string', 'max:64'],
            'openai_analysis_model' => ['nullable', 'string', 'max:64'],
            'message_actions' => ['nullable', 'array'],
            'message_actions.tow_company' => ['nullable', 'string', 'max:120'],
            'message_actions.tow_phone' => ['nullable', 'string', 'max:32'],
            'message_actions.tow_notes' => ['nullable', 'string', 'max:500'],
            'message_actions.wifi_ssid' => ['nullable', 'string', 'max:120'],
            'message_actions.wifi_password' => ['nullable', 'string', 'max:120'],
            'message_actions.after_hours_pickup' => ['nullable', 'string', 'max:500'],
            'message_actions.colors' => ['nullable', 'array'],
            'message_actions.colors.*' => ['nullable', Rule::in(CommunicationsAccentColor::keys())],
            'quick_reply_templates_present' => ['nullable', 'boolean'],
            'quick_reply_templates' => ['nullable', 'array', 'max:40'],
            'quick_reply_templates.*.label' => ['required', 'string', 'max:40'],
            'quick_reply_templates.*.body' => ['required', 'string', 'max:1600'],
            'quick_reply_templates.*.color' => ['nullable', Rule::in(CommunicationsAccentColor::keys())],
        ]);

        $callFlowInput = is_array($data['telephony_call_flow'] ?? null) ? $data['telephony_call_flow'] : [];
        $existingFlow = TelephonyCallFlowSettings::fromShopSettings(ShopSettings::current())->toArray();
        $platformVoiceManaged = PlatformConnection::current()->isConnected();

        // Hosted Voice: phone schedule/recording/greetings/dial timeout are Platform-authored.
        if ($platformVoiceManaged && in_array($communicationsTab, ['hours', 'recording', 'ring'], true)) {
            return redirect()
                ->route('operations.settings.shop.edit', [
                    'section' => 'communications',
                    'communications-tab' => $communicationsTab,
                ])
                ->with('status', 'Phone settings are managed in ARK Cloud.');
        }

        $weeklyInput = is_array($callFlowInput['weekly_hours'] ?? null) ? $callFlowInput['weekly_hours'] : [];

        foreach (TelephonyCallFlowSettings::WEEKDAYS as $day) {
            $dayInput = is_array($weeklyInput[$day] ?? null) ? $weeklyInput[$day] : [];
            $existingFlow['weekly_hours'][$day] = [
                'enabled' => filter_var($dayInput['enabled'] ?? $existingFlow['weekly_hours'][$day]['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'open' => $dayInput['open'] ?? $existingFlow['weekly_hours'][$day]['open'] ?? '09:00',
                'close' => $dayInput['close'] ?? $existingFlow['weekly_hours'][$day]['close'] ?? '18:00',
            ];
        }

        $closedDates = array_key_exists('closed_dates', $callFlowInput)
            ? collect(preg_split('/\r\n|\r|\n/', (string) $callFlowInput['closed_dates']) ?: [])
                ->map(fn (string $date): string => trim($date))
                ->filter(fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
                ->unique()
                ->sort()
                ->values()
                ->all()
            : ($existingFlow['closed_dates'] ?? []);

        $hoursBypassNumbers = array_key_exists('hours_bypass_numbers', $callFlowInput)
            ? collect(preg_split('/\r\n|\r|\n/', (string) $callFlowInput['hours_bypass_numbers']) ?: [])
                ->map(fn (string $number): string => trim($number))
                ->filter(fn (string $number): bool => $number !== '')
                ->map(fn (string $number): ?string => PhoneNumber::normalize($number))
                ->filter(fn (?string $number): bool => $number !== null && strlen($number) === 10)
                ->unique()
                ->values()
                ->all()
            : ($existingFlow['hours_bypass_numbers'] ?? []);

        $shopTimezone = filled($data['shop_timezone'] ?? null)
            ? trim((string) $data['shop_timezone'])
            : (filled($callFlowInput['timezone'] ?? null)
                ? trim((string) $callFlowInput['timezone'])
                : (string) ShopSettings::current()->shop_timezone);

        $callFlow = [
            'timezone' => $shopTimezone,
            'weekly_hours' => $existingFlow['weekly_hours'],
            'closed_dates' => $closedDates,
            'hours_bypass_numbers' => $hoursBypassNumbers,
            'voicemail_greeting' => filled($callFlowInput['voicemail_greeting'] ?? null)
                ? trim((string) $callFlowInput['voicemail_greeting'])
                : $existingFlow['voicemail_greeting'],
            'closed_greeting' => filled($callFlowInput['closed_greeting'] ?? null)
                ? trim((string) $callFlowInput['closed_greeting'])
                : $existingFlow['closed_greeting'],
            'recording_disclaimer' => filled($callFlowInput['recording_disclaimer'] ?? null)
                ? trim((string) $callFlowInput['recording_disclaimer'])
                : $existingFlow['recording_disclaimer'],
            'cell_whisper_prompt' => array_key_exists('cell_whisper_prompt', $callFlowInput)
                ? trim((string) ($callFlowInput['cell_whisper_prompt'] ?? ''))
                : ($existingFlow['cell_whisper_prompt'] ?? ''),
            'caller_ring_tone' => array_key_exists('caller_ring_audio_mode', $callFlowInput)
                ? TelephonyCallerRingtone::normalizeFromSettingsInput(
                    isset($callFlowInput['caller_ring_audio_mode']) ? (string) $callFlowInput['caller_ring_audio_mode'] : null,
                    isset($callFlowInput['caller_ring_promo_url']) ? (string) $callFlowInput['caller_ring_promo_url'] : null,
                    $existingFlow['caller_ring_tone'] ?? null,
                )
                : ($existingFlow['caller_ring_tone'] ?? 'us'),
            'record_inbound_calls' => $request->boolean(
                'telephony_call_flow.record_inbound_calls',
                (bool) ($existingFlow['record_inbound_calls'] ?? false),
            ),
            'record_outbound_calls' => $request->boolean(
                'telephony_call_flow.record_outbound_calls',
                (bool) ($existingFlow['record_outbound_calls'] ?? false),
            ),
            'dial_timeout_seconds' => (int) ($callFlowInput['dial_timeout_seconds'] ?? $existingFlow['dial_timeout_seconds'] ?? 25),
            'owned_popup_timeout_seconds' => (int) ($callFlowInput['owned_popup_timeout_seconds'] ?? $existingFlow['owned_popup_timeout_seconds'] ?? 8),
            'presence_timeout_minutes' => (int) ($callFlowInput['presence_timeout_minutes'] ?? $existingFlow['presence_timeout_minutes'] ?? 30),
            'comms_attention_gate_enabled' => $request->boolean(
                'telephony_call_flow.comms_attention_gate_enabled',
                (bool) ($existingFlow['comms_attention_gate_enabled'] ?? true),
            ),
            'comms_escalation_enabled' => $request->boolean(
                'telephony_call_flow.comms_escalation_enabled',
                (bool) ($existingFlow['comms_escalation_enabled'] ?? true),
            ),
            'comms_escalation_delay_minutes' => (int) ($callFlowInput['comms_escalation_delay_minutes'] ?? $existingFlow['comms_escalation_delay_minutes'] ?? 3),
            'comms_escalation_cooldown_minutes' => (int) ($callFlowInput['comms_escalation_cooldown_minutes'] ?? $existingFlow['comms_escalation_cooldown_minutes'] ?? 30),
            'comms_browser_notifications_enabled' => $request->boolean(
                'telephony_call_flow.comms_browser_notifications_enabled',
                (bool) ($existingFlow['comms_browser_notifications_enabled'] ?? true),
            ),
            'missed_call_rescue_enabled' => $request->boolean(
                'telephony_call_flow.missed_call_rescue_enabled',
                (bool) ($existingFlow['missed_call_rescue_enabled'] ?? false),
            ),
            'missed_call_rescue_delay_seconds' => (int) ($callFlowInput['missed_call_rescue_delay_seconds'] ?? $existingFlow['missed_call_rescue_delay_seconds'] ?? 120),
            'missed_call_rescue_cooldown_minutes' => (int) ($callFlowInput['missed_call_rescue_cooldown_minutes'] ?? $existingFlow['missed_call_rescue_cooldown_minutes'] ?? 60),
            'missed_call_rescue_text_open' => array_key_exists('missed_call_rescue_text_open', $callFlowInput)
                ? trim((string) ($callFlowInput['missed_call_rescue_text_open'] ?? ''))
                : (string) ($existingFlow['missed_call_rescue_text_open'] ?? ''),
            'missed_call_rescue_text_closed' => array_key_exists('missed_call_rescue_text_closed', $callFlowInput)
                ? trim((string) ($callFlowInput['missed_call_rescue_text_closed'] ?? ''))
                : (string) ($existingFlow['missed_call_rescue_text_closed'] ?? ''),
        ];

        if ($platformVoiceManaged) {
            // Preserve last Core copy of managed Voice keys; Platform is authoring authority.
            foreach ([
                'weekly_hours',
                'closed_dates',
                'hours_bypass_numbers',
                'voicemail_greeting',
                'closed_greeting',
                'recording_disclaimer',
                'record_inbound_calls',
                'dial_timeout_seconds',
                'cell_whisper_prompt',
                'caller_ring_tone',
            ] as $managedKey) {
                $callFlow[$managedKey] = $existingFlow[$managedKey] ?? $callFlow[$managedKey];
            }
        }

        $settings = ShopSettings::current();
        $settingsUpdates = [
            'telephony_call_flow' => $callFlow,
            'shop_timezone' => $shopTimezone,
        ];

        if ($communicationsTab === 'general') {
            if (! $platformVoiceManaged) {
                $settingsUpdates['telephony_inbound_number'] = filled($data['telephony_inbound_number'] ?? null)
                    ? trim((string) $data['telephony_inbound_number'])
                    : null;
                $settingsUpdates['telephony_provider'] = TelephonyProviderType::Twilio->value;
                $settingsUpdates['twilio_api_key_sid'] = $this->nullableTrimmedString($data['twilio_api_key_sid'] ?? null);
                $this->mergeSecretField($settingsUpdates, 'twilio_api_key_secret', $data['twilio_api_key_secret'] ?? null);
                $settingsUpdates['twilio_voice_twiml_app_sid'] = $this->nullableTrimmedString($data['twilio_voice_twiml_app_sid'] ?? null);
                $settingsUpdates['twilio_fcm_credential_sid'] = $this->nullableTrimmedString($data['twilio_fcm_credential_sid'] ?? null);
                $settingsUpdates['twilio_apns_voip_credential_sid'] = $this->nullableTrimmedString($data['twilio_apns_voip_credential_sid'] ?? null);
            }

            // Account SID/token remain until SMS/playback leave Core (Communications migration).
            $settingsUpdates['twilio_account_sid'] = $this->nullableTrimmedString($data['twilio_account_sid'] ?? null);
            $this->mergeSecretField($settingsUpdates, 'twilio_auth_token', $data['twilio_auth_token'] ?? null);

            $messageActionsInput = is_array($data['message_actions'] ?? null) ? $data['message_actions'] : [];
            $colorInput = is_array($messageActionsInput['colors'] ?? null) ? $messageActionsInput['colors'] : [];
            $colors = [];
            foreach (MessageActionKey::advisorOneTap() as $action) {
                $colors[$action->value] = CommunicationsAccentColor::normalize($colorInput[$action->value] ?? null);
            }
            $settingsUpdates['message_actions'] = [
                'tow_company' => $this->nullableTrimmedString($messageActionsInput['tow_company'] ?? null),
                'tow_phone' => $this->nullableTrimmedString($messageActionsInput['tow_phone'] ?? null),
                'tow_notes' => $this->nullableTrimmedString($messageActionsInput['tow_notes'] ?? null),
                'wifi_ssid' => $this->nullableTrimmedString($messageActionsInput['wifi_ssid'] ?? null),
                'wifi_password' => $this->nullableTrimmedString($messageActionsInput['wifi_password'] ?? null),
                'after_hours_pickup' => $this->nullableTrimmedString($messageActionsInput['after_hours_pickup'] ?? null),
                'colors' => $colors,
            ];

            if ($request->boolean('quick_reply_templates_present')) {
                $settingsUpdates['quick_reply_templates'] = CommunicationsQuickReplyTemplates::normalize(
                    is_array($data['quick_reply_templates'] ?? null) ? $data['quick_reply_templates'] : [],
                );
            }
        }

        if ($communicationsTab === 'recording' && ! $platformVoiceManaged) {
            $this->mergeSecretField($settingsUpdates, 'openai_api_key', $data['openai_api_key'] ?? null);
            $settingsUpdates['openai_transcription_model'] = $this->nullableTrimmedString($data['openai_transcription_model'] ?? null)
                ?: 'whisper-1';
            $settingsUpdates['openai_analysis_model'] = $this->nullableTrimmedString($data['openai_analysis_model'] ?? null)
                ?: 'gpt-4o-mini';
        }

        $settings->persistTrusted($settingsUpdates);
        ShopDisplayTimezone::apply();

        if ($communicationsTab === 'ring' && ! $platformVoiceManaged) {
            $endpoints = $staffPhoneForRing->applyAndValidate($data['endpoints'] ?? []);
            $endpointSync->sync($endpoints);
        }

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'communications',
                'communications-tab' => $communicationsTab,
            ])
            ->with('status', 'Telephony settings saved.');
    }

    private function updateCommunicationsInfrastructure(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isMasterAdmin(), 403);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'runtime-health'])
            ->with('status', 'Communications infrastructure is managed through Twilio settings.');
    }

    private function updateMobilePush(Request $request): RedirectResponse
    {
        $request->validate([
            'mobile_push' => ['nullable', 'array'],
            'mobile_push.enabled' => ['nullable', 'boolean'],
            'twilio_api_key_sid' => ['nullable', 'string', 'max:64'],
            'twilio_api_key_secret' => ['nullable', 'string', 'max:128'],
            'twilio_voice_twiml_app_sid' => ['nullable', 'string', 'max:64'],
            'twilio_fcm_credential_sid' => ['nullable', 'string', 'max:64'],
            'twilio_apns_voip_credential_sid' => ['nullable', 'string', 'max:64'],
        ]);

        $settings = ShopSettings::current();
        $existing = MobilePushSettings::fromShopSettings($settings);
        $platformVoiceManaged = PlatformConnection::current()->isConnected();
        $settingsUpdates = [
            'mobile_push' => [
                'enabled' => $request->boolean(
                    'mobile_push.enabled',
                    $existing->enabled,
                ),
            ],
        ];

        if (! $platformVoiceManaged) {
            $settingsUpdates['twilio_api_key_sid'] = $this->nullableTrimmedString($request->input('twilio_api_key_sid'));
            $this->mergeSecretField($settingsUpdates, 'twilio_api_key_secret', $request->input('twilio_api_key_secret'));
            $settingsUpdates['twilio_voice_twiml_app_sid'] = $this->nullableTrimmedString($request->input('twilio_voice_twiml_app_sid'));
            $settingsUpdates['twilio_fcm_credential_sid'] = $this->nullableTrimmedString($request->input('twilio_fcm_credential_sid'));
            $settingsUpdates['twilio_apns_voip_credential_sid'] = $this->nullableTrimmedString($request->input('twilio_apns_voip_credential_sid'));
        }

        $settings->persistTrusted($settingsUpdates);
        Cache::forget('mobile:fcm:access_token');

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'communications',
                'communications-tab' => 'mobile',
            ])
            ->with('status', 'Mobile settings saved.');
    }

    private function updateCommunicationsChannels(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channels' => ['nullable', 'array'],
            'channels.messenger.enabled' => ['nullable', 'boolean'],
            'channels.messenger.page_id' => ['nullable', 'string', 'max:64'],
            'channels.messenger.page_name' => ['nullable', 'string', 'max:120'],
            'channels.messenger.page_access_token' => ['nullable', 'string', 'max:2048'],
            'channels.messenger.outside_window_tag' => ['nullable', 'string', Rule::enum(MetaMessengerMessageTag::class)],
        ]);

        $existing = CommunicationsChannelSettings::fromShopSettings(ShopSettings::current());
        $messengerInput = is_array($data['channels']['messenger'] ?? null) ? $data['channels']['messenger'] : [];

        $pageId = $this->nullableTrimmedString($messengerInput['page_id'] ?? null);
        $pageToken = filled($messengerInput['page_access_token'] ?? null)
            ? trim((string) $messengerInput['page_access_token'])
            : $existing->messengerPageAccessToken;

        $outsideTag = array_key_exists('outside_window_tag', $messengerInput)
            ? (filled($messengerInput['outside_window_tag'] ?? null)
                ? MetaMessengerMessageTag::from((string) $messengerInput['outside_window_tag'])->value
                : null)
            : $existing->messengerOutsideWindowTag?->value;

        $channels = is_array(ShopSettings::current()->communications_channels)
            ? ShopSettings::current()->communications_channels
            : [];

        // Preserve legacy verify_token in JSON for platform fallback until env is set — do not accept new shop writes.
        $legacyVerify = is_array($channels['messenger'] ?? null)
            ? ($channels['messenger']['verify_token'] ?? null)
            : null;

        $settings = ShopSettings::current();

        DB::transaction(function () use (
            $settings,
            $request,
            $pageId,
            $pageToken,
            $outsideTag,
            $messengerInput,
            $existing,
            $legacyVerify,
        ): void {
            $settings->persistTrusted([
                'communications_channels' => [
                    'messenger' => array_filter([
                        'enabled' => $request->boolean('channels.messenger.enabled'),
                        'page_id' => $pageId,
                        'page_name' => $this->nullableTrimmedString($messengerInput['page_name'] ?? null)
                            ?? $existing->messengerPageName,
                        'outside_window_tag' => $outsideTag,
                        'verify_token' => filled($legacyVerify) ? $legacyVerify : null,
                    ], fn ($value) => $value !== null),
                ],
                'messenger_page_id' => $pageId,
                'messenger_page_access_token' => $pageToken,
            ]);
        });

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'communications',
                'communications-tab' => 'messenger',
            ])
            ->with('status', 'Messenger settings saved.');
    }
}
