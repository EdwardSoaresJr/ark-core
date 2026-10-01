<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class TelephonyCallFlowSettings
{
    /** @var list<string> */
    public const WEEKDAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function __construct(
        private readonly array $config,
    ) {}

    public static function fromShopSettings(?ShopSettings $settings = null): self
    {
        $settings ??= ShopSettings::current();
        $stored = $settings->telephony_call_flow;
        $defaults = ShopSettings::defaultTelephonyCallFlow();

        if (! is_array($stored) || $stored === []) {
            return new self($defaults);
        }

        $merged = array_merge($defaults, $stored);
        $storedWeekly = is_array($stored['weekly_hours'] ?? null) ? $stored['weekly_hours'] : [];

        if ($storedWeekly === []) {
            $merged['weekly_hours'] = $defaults['weekly_hours'];
        } else {
            $weekly = $defaults['weekly_hours'];
            foreach (self::WEEKDAYS as $day) {
                if (! isset($storedWeekly[$day]) || ! is_array($storedWeekly[$day])) {
                    continue;
                }

                $weekly[$day] = [
                    'enabled' => filter_var(
                        $storedWeekly[$day]['enabled'] ?? $weekly[$day]['enabled'],
                        FILTER_VALIDATE_BOOL,
                    ),
                    'open' => (string) ($storedWeekly[$day]['open'] ?? $weekly[$day]['open']),
                    'close' => (string) ($storedWeekly[$day]['close'] ?? $weekly[$day]['close']),
                ];
            }
            $merged['weekly_hours'] = $weekly;
        }

        return new self($merged);
    }

    public function timezone(): string
    {
        return ShopDisplayTimezone::resolve();
    }

    public function dialTimeoutSeconds(): int
    {
        return max(10, min(60, (int) ($this->config['dial_timeout_seconds'] ?? 25)));
    }

    public function presenceTimeoutMinutes(): int
    {
        return max(5, min(240, (int) ($this->config['presence_timeout_minutes'] ?? 30)));
    }

    public function ownedPopupTimeoutSeconds(): int
    {
        return max(3, min(60, (int) ($this->config['owned_popup_timeout_seconds'] ?? 8)));
    }

    public function attentionGateEnabled(): bool
    {
        return filter_var($this->config['comms_attention_gate_enabled'] ?? true, FILTER_VALIDATE_BOOL);
    }

    public function escalationEnabled(): bool
    {
        return filter_var($this->config['comms_escalation_enabled'] ?? true, FILTER_VALIDATE_BOOL);
    }

    public function escalationDelayMinutes(): int
    {
        return max(1, min(30, (int) ($this->config['comms_escalation_delay_minutes'] ?? 3)));
    }

    public function escalationCooldownMinutes(): int
    {
        return max(5, min(240, (int) ($this->config['comms_escalation_cooldown_minutes'] ?? 30)));
    }

    public function browserNotificationsEnabled(): bool
    {
        return filter_var($this->config['comms_browser_notifications_enabled'] ?? true, FILTER_VALIDATE_BOOL);
    }

    public function missedCallRescueEnabled(): bool
    {
        return filter_var($this->config['missed_call_rescue_enabled'] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function missedCallRescueDelaySeconds(): int
    {
        return max(10, min(3600, (int) ($this->config['missed_call_rescue_delay_seconds'] ?? 120)));
    }

    public function missedCallRescueCooldownMinutes(): int
    {
        return max(30, min(4320, (int) ($this->config['missed_call_rescue_cooldown_minutes'] ?? 60)));
    }

    public function missedCallRescueTextOpen(): string
    {
        return trim((string) ($this->config['missed_call_rescue_text_open'] ?? ''));
    }

    public function missedCallRescueTextClosed(): string
    {
        return trim((string) ($this->config['missed_call_rescue_text_closed'] ?? ''));
    }

    public function recordInboundCalls(): bool
    {
        return filter_var($this->config['record_inbound_calls'] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function staggeredRingLeadSeconds(): int
    {
        return $this->recordInboundCalls() ? 5 : 2;
    }

    public function recordOutboundCalls(): bool
    {
        return filter_var($this->config['record_outbound_calls'] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function recordingDisclaimer(): string
    {
        $disclaimer = trim((string) ($this->config['recording_disclaimer'] ?? ''));

        if ($disclaimer !== '') {
            return $disclaimer;
        }

        return 'This call may be recorded for quality and training purposes.';
    }

    public function callerRingTone(): string
    {
        $tone = trim((string) ($this->config['caller_ring_tone'] ?? ''));

        if ($tone === '') {
            return 'us';
        }

        if (preg_match('/^https?:\/\//i', $tone) === 1) {
            return $tone;
        }

        return strtolower($tone);
    }

    public function cellWhisperPrompt(?ShopSettings $settings = null): string
    {
        $custom = trim((string) ($this->config['cell_whisper_prompt'] ?? ''));

        if ($custom !== '') {
            return $custom;
        }

        $settings ??= ShopSettings::current();
        $shopName = trim((string) ($settings->shop_name ?? ''));

        if ($shopName !== '') {
            return 'Call for '.$shopName;
        }

        return 'Incoming shop call';
    }

    public function voicemailGreeting(): string
    {
        $greeting = trim((string) ($this->config['voicemail_greeting'] ?? ''));

        if ($greeting !== '') {
            return $greeting;
        }

        return 'Thank you for calling. We are unable to take your call right now. Please leave a message after the tone.';
    }

    public function closedGreeting(): string
    {
        $greeting = trim((string) ($this->config['closed_greeting'] ?? ''));

        if ($greeting !== '') {
            return $greeting;
        }

        return 'Thank you for calling. We are currently closed. Please leave a message after the tone.';
    }

    /**
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    public function weeklyHours(): array
    {
        $weekly = $this->config['weekly_hours'] ?? [];
        $resolved = [];

        foreach (self::WEEKDAYS as $day) {
            $dayConfig = is_array($weekly[$day] ?? null) ? $weekly[$day] : [];
            $resolved[$day] = [
                'enabled' => filter_var($dayConfig['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'open' => $this->normalizeTime((string) ($dayConfig['open'] ?? '09:00'), '09:00'),
                'close' => $this->normalizeTime((string) ($dayConfig['close'] ?? '18:00'), '18:00'),
            ];
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    public function closedDates(): array
    {
        $dates = $this->config['closed_dates'] ?? [];

        if (! is_array($dates)) {
            return [];
        }

        return collect($dates)
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter(fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string> Normalized 10-digit numbers allowed to ring when the shop is closed.
     */
    public function hoursBypassNumbers(): array
    {
        $numbers = $this->config['hours_bypass_numbers'] ?? [];

        if (! is_array($numbers)) {
            return [];
        }

        return collect($numbers)
            ->map(fn (mixed $number): ?string => PhoneNumber::normalize((string) $number))
            ->filter(fn (?string $number): bool => $number !== null && strlen($number) === 10)
            ->unique()
            ->values()
            ->all();
    }

    public function callerBypassesClosedHours(?string $caller): bool
    {
        $normalized = PhoneNumber::normalize($caller);

        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, $this->hoursBypassNumbers(), true);
    }

    public function isOpenForCaller(?string $caller, ?CarbonImmutable $moment = null): bool
    {
        if ($this->callerBypassesClosedHours($caller)) {
            return true;
        }

        return $this->isOpenAt($moment);
    }

    public function isOpenAt(?CarbonImmutable $moment = null): bool
    {
        $moment ??= CarbonImmutable::now($this->timezone());
        $date = $moment->format('Y-m-d');

        if (in_array($date, $this->closedDates(), true)) {
            return false;
        }

        $dayKey = strtolower($moment->englishDayOfWeek);
        $hours = $this->weeklyHours()[$dayKey] ?? null;

        if ($hours === null || ! $hours['enabled']) {
            return false;
        }

        $open = $moment->setTimeFromTimeString($hours['open']);
        $close = $moment->setTimeFromTimeString($hours['close']);

        return $moment->greaterThanOrEqualTo($open) && $moment->lessThan($close);
    }

    public function isOpenDay(Carbon|CarbonImmutable $day, ?string $timezone = null): bool
    {
        $timezone ??= $this->timezone();
        $moment = ($day instanceof CarbonImmutable ? $day : CarbonImmutable::instance($day))
            ->timezone($timezone)
            ->startOfDay();

        if (in_array($moment->toDateString(), $this->closedDates(), true)) {
            return false;
        }

        $dayKey = strtolower($moment->englishDayOfWeek);
        $hours = $this->weeklyHours()[$dayKey] ?? null;

        return $hours !== null && ($hours['enabled'] ?? false);
    }

    /**
     * Shop-local business-hours open instant for the given day, or null when the
     * shop is closed that day (weekly hours disabled or an explicit closed date).
     */
    public function openAtForDay(Carbon|CarbonImmutable $day, ?string $timezone = null): ?CarbonImmutable
    {
        $timezone ??= $this->timezone();

        if (! $this->isOpenDay($day, $timezone)) {
            return null;
        }

        $moment = $this->dayStart($day, $timezone);
        $hours = $this->weeklyHours()[strtolower($moment->englishDayOfWeek)] ?? null;

        return $hours === null ? null : $moment->setTimeFromTimeString($hours['open']);
    }

    /**
     * Shop-local business-hours close instant for the given day, or null when the
     * shop is closed that day.
     */
    public function closeAtForDay(Carbon|CarbonImmutable $day, ?string $timezone = null): ?CarbonImmutable
    {
        $timezone ??= $this->timezone();

        if (! $this->isOpenDay($day, $timezone)) {
            return null;
        }

        $moment = $this->dayStart($day, $timezone);
        $hours = $this->weeklyHours()[strtolower($moment->englishDayOfWeek)] ?? null;

        return $hours === null ? null : $moment->setTimeFromTimeString($hours['close']);
    }

    private function dayStart(Carbon|CarbonImmutable $day, string $timezone): CarbonImmutable
    {
        return ($day instanceof CarbonImmutable ? $day : CarbonImmutable::instance($day))
            ->timezone($timezone)
            ->startOfDay();
    }

    public function openDayCount(Carbon $from, Carbon $to, ?string $timezone = null): int
    {
        $timezone ??= $this->timezone();
        $day = $from->copy()->timezone($timezone)->startOfDay();
        $last = $to->copy()->timezone($timezone)->startOfDay();
        $count = 0;

        while ($day->lte($last)) {
            if ($this->isOpenDay($day, $timezone)) {
                $count++;
            }

            $day->addDay();
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'timezone' => $this->timezone(),
            'weekly_hours' => $this->weeklyHours(),
            'closed_dates' => $this->closedDates(),
            'hours_bypass_numbers' => $this->hoursBypassNumbers(),
            'voicemail_greeting' => $this->voicemailGreeting(),
            'closed_greeting' => $this->closedGreeting(),
            'recording_disclaimer' => $this->recordingDisclaimer(),
            'cell_whisper_prompt' => trim((string) ($this->config['cell_whisper_prompt'] ?? '')),
            'caller_ring_tone' => $this->callerRingTone(),
            'record_inbound_calls' => $this->recordInboundCalls(),
            'record_outbound_calls' => $this->recordOutboundCalls(),
            'dial_timeout_seconds' => $this->dialTimeoutSeconds(),
            'presence_timeout_minutes' => $this->presenceTimeoutMinutes(),
            'owned_popup_timeout_seconds' => $this->ownedPopupTimeoutSeconds(),
            'comms_attention_gate_enabled' => $this->attentionGateEnabled(),
            'comms_escalation_enabled' => $this->escalationEnabled(),
            'comms_escalation_delay_minutes' => $this->escalationDelayMinutes(),
            'comms_escalation_cooldown_minutes' => $this->escalationCooldownMinutes(),
            'comms_browser_notifications_enabled' => $this->browserNotificationsEnabled(),
            'missed_call_rescue_enabled' => $this->missedCallRescueEnabled(),
            'missed_call_rescue_delay_seconds' => $this->missedCallRescueDelaySeconds(),
            'missed_call_rescue_cooldown_minutes' => $this->missedCallRescueCooldownMinutes(),
            'missed_call_rescue_text_open' => $this->missedCallRescueTextOpen(),
            'missed_call_rescue_text_closed' => $this->missedCallRescueTextClosed(),
        ];
    }

    private function normalizeTime(string $value, string $fallback): string
    {
        $value = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return $fallback;
    }
}
