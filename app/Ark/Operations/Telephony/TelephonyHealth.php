<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Broadcast\ReverbDeployment;
use Illuminate\Support\Carbon;

final class TelephonyHealth
{
    public const WEBHOOK_RECEIVED_CACHE_KEY = 'telephony:last-webhook-at';

    public function __construct(
        private readonly ShopSettings $settings,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(ShopSettings::current(), ShopIntegrationCredentials::forCurrentShop());
    }

    public function primaryProvider(): TelephonyProviderType
    {
        return TelephonyShopSettings::fromShopSettings($this->settings)->primaryProvider;
    }

    public function providerLabel(): string
    {
        return 'Voice transport not configured';
    }

    public function voiceIngressTone(): string
    {
        return $this->connectionTone();
    }

    public function voiceIngressLabel(): string
    {
        return $this->connectionLabel();
    }

    public function voiceSignalTone(): string
    {
        return $this->webhookTone();
    }

    public function voiceSignalLabel(): string
    {
        return $this->webhookLabel();
    }

    public function lastVoiceSignalAt(): ?Carbon
    {
        return $this->lastWebhookAt();
    }

    public function providerTone(string $smsWebhookTone): string
    {
        return 'muted';
    }

    private function rollupConnectionTone(): string
    {
        return 'muted';
    }

    /**
     * @param  list<string>  $tones
     */
    private function worstTone(array $tones): string
    {
        $tones = array_values(array_filter(
            $tones,
            fn (string $tone): bool => $tone !== 'muted',
        ));

        if ($tones === []) {
            return 'muted';
        }

        if (in_array('danger', $tones, true)) {
            return 'danger';
        }

        if (in_array('warning', $tones, true)) {
            return 'warning';
        }

        return 'success';
    }

    public function credentialsConfigured(): bool
    {
        return $this->credentials->messagingConfigured();
    }

    public function accountSidConfigured(): bool
    {
        return $this->credentials->messagingConfigured();
    }

    public function credentialSourceLabel(): string
    {
        return match ($this->credentials->twilioCredentialSource()) {
            'database' => 'Saved in Settings',
            'env' => 'Loaded from server .env fallback',
            default => 'Not configured',
        };
    }

    public function connectionState(): string
    {
        if (! $this->credentials->twilioConfigured()) {
            return 'not_connected';
        }

        if (! app(TelephonyRingGroup::class)->hasRingTargets($this->settings)) {
            return 'needs_forward_number';
        }

        return 'connected';
    }

    public function connectionLabel(): string
    {
        return match ($this->connectionState()) {
            'connected' => 'Connected',
            'needs_forward_number' => 'Needs ring endpoint',
            default => 'Not connected',
        };
    }

    public function connectionTone(): string
    {
        return match ($this->connectionState()) {
            'connected' => 'success',
            'needs_forward_number' => 'warning',
            default => 'danger',
        };
    }

    public function shopNumberRaw(): ?string
    {
        if (filled($this->settings->telephony_inbound_number)) {
            return trim((string) $this->settings->telephony_inbound_number);
        }

        $recentToNumber = CallSession::query()
            ->whereNotNull('to_number')
            ->latest('id')
            ->value('to_number');

        return filled($recentToNumber) ? trim((string) $recentToNumber) : null;
    }

    public function shopNumberDisplay(): ?string
    {
        $raw = $this->shopNumberRaw();

        return $raw !== null ? PhoneNumber::display($raw) : null;
    }

    public function forwardNumberDisplay(): ?string
    {
        $source = TelephonyForwardNumber::displaySource($this->settings);

        return $source !== null ? PhoneNumber::display($source) : null;
    }

    public function forwardNumberSourceLabel(): string
    {
        return TelephonyForwardNumber::sourceLabel($this->settings);
    }

    /**
     * @return list<string>
     */
    public function ringEndpointLabels(): array
    {
        $labels = TelephonyEndpoint::query()
            ->where('enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (TelephonyEndpoint $endpoint): string => $endpoint->name)
            ->values()
            ->all();

        if ($labels !== []) {
            return $labels;
        }

        $legacy = $this->forwardNumberDisplay();

        return $legacy !== null ? [$legacy] : [];
    }

    public function ringTargetsSummary(): ?string
    {
        $labels = $this->ringEndpointLabels();

        return $labels === [] ? null : implode(', ', $labels);
    }

    public function webhookUrl(): string
    {
        return '';
    }

    public function sipOutboundWebhookUrl(): string
    {
        return '';
    }

    public function clientOutboundWebhookUrl(): string
    {
        return '';
    }

    public function clientIncomingWebhookUrl(): string
    {
        return '';
    }

    public function mobileVoiceClientConfigured(): bool
    {
        return false;
    }

    public function mobileVoiceTwimlAppPresent(): bool
    {
        return false;
    }

    public function mobileVoiceIosVoipPushCredentialPresent(): bool
    {
        return false;
    }

    public function mobileVoiceAndroidFcmCredentialPresent(): bool
    {
        return false;
    }

    public function mobileVoiceClientInboundEnabled(): bool
    {
        return false;
    }

    public function mobileVoiceClientLabel(): string
    {
        if (! $this->mobileVoiceClientConfigured()) {
            return 'Setup needed';
        }

        if (! $this->mobileVoiceClientInboundEnabled()) {
            return 'Outbound only · push SID needed';
        }

        return 'Ready';
    }

    public function mobileVoiceClientTone(): string
    {
        if (! $this->mobileVoiceClientConfigured()) {
            return 'warning';
        }

        return $this->mobileVoiceClientInboundEnabled() ? 'success' : 'warning';
    }

    /**
     * @return list<array{label: string, hint: string, url: string}>
     */
    public function mobileVoiceClientWebhookRows(): array
    {
        return [
            [
                'label' => 'Client outbound',
                'hint' => 'TwiML App → Voice URL (ARK Phone outbound)',
                'url' => $this->clientOutboundWebhookUrl(),
            ],
            [
                'label' => 'Client inbound',
                'hint' => 'Client-to-client bridge (optional)',
                'url' => $this->clientIncomingWebhookUrl(),
            ],
        ];
    }

    public function lastWebhookAt(): ?Carbon
    {
        $cached = cache()->get(self::WEBHOOK_RECEIVED_CACHE_KEY);

        if ($cached instanceof Carbon) {
            return $cached;
        }

        if (is_string($cached) && $cached !== '') {
            return Carbon::parse($cached);
        }

        $sessionStartedAt = CallSession::query()->latest('id')->value('started_at');

        return $sessionStartedAt instanceof Carbon ? $sessionStartedAt : null;
    }

    public function webhookState(): string
    {
        if (! $this->credentialsConfigured()) {
            return 'error';
        }

        if ($this->lastWebhookAt() === null) {
            return 'waiting';
        }

        return 'healthy';
    }

    public function webhookLabel(): string
    {
        return match ($this->webhookState()) {
            'healthy' => 'Healthy',
            'waiting' => 'Waiting for first call',
            default => 'Error',
        };
    }

    public function webhookTone(): string
    {
        return match ($this->webhookState()) {
            'healthy' => 'success',
            'waiting' => 'warning',
            default => 'danger',
        };
    }

    public function reverbConfigured(): bool
    {
        return IncomingCallBroadcast::enabled();
    }

    public function reverbClientConfigured(): bool
    {
        return filled(config('broadcasting.connections.reverb.key'));
    }

    public function reverbWebsocketUrl(): ?string
    {
        if (! $this->reverbConfigured()) {
            return null;
        }

        return ReverbDeployment::websocketUrl();
    }

    public function reverbHostSourceLabel(): string
    {
        return match (ReverbDeployment::publicHostSource()) {
            'REVERB_HOST' => 'REVERB_HOST',
            'APP_URL' => 'APP_URL',
            default => 'default',
        };
    }

    public function reverbLabel(): string
    {
        if (! $this->reverbConfigured()) {
            return 'Off';
        }

        return $this->reverbClientConfigured()
            ? 'Live · '.ReverbDeployment::websocketUrl()
            : 'Broadcast on, client keys missing';
    }

    public function reverbTone(): string
    {
        if (! $this->reverbConfigured()) {
            return 'muted';
        }

        return $this->reverbClientConfigured() ? 'success' : 'warning';
    }

    public function lastIncomingCall(): ?CallSession
    {
        return CallSession::query()
            ->with('customer')
            ->latest('started_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastScreenPop(): ?array
    {
        $payload = cache()->get(IncomingCallContextBroadcaster::cacheKey());

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return list<string>
     */
    public function operationalNotes(): array
    {
        $notes = [];

        if (! $this->credentialsConfigured()) {
            $notes[] = 'Twilio auth token is missing from deployment configuration.';
        }

        if (
            $this->credentials->twilioConfigured()
            && ! app(TelephonyRingGroup::class)->hasRingTargets($this->settings)
        ) {
            $notes[] = 'Incoming calls cannot ring until at least one enabled endpoint is saved.';
        }

        $cellEndpointsMissingPhone = TelephonyEndpoint::query()
            ->with('user')
            ->where('enabled', true)
            ->where('type', TelephonyEndpointType::Cell)
            ->get()
            ->filter(fn (TelephonyEndpoint $endpoint): bool => $endpoint->resolvedUserPhone() === '' && $endpoint->dialDestination() === '')
            ->pluck('name')
            ->all();

        if ($cellEndpointsMissingPhone !== []) {
            $notes[] = 'Cell endpoints missing a staff phone will not ring: '.implode(', ', $cellEndpointsMissingPhone).'. Enter the number on the cell endpoint here or under Settings → Staff.';
        }

        if ($this->credentials->twilioConfigured() && ! filled($this->settings->telephony_inbound_number)) {
            $notes[] = 'Save the business number so staff know which line customers are calling.';
            $notes[] = 'Outbound calls from SIP desk phones also need the business number saved for caller ID.';
        }

        if ($this->reverbConfigured() && ! $this->reverbClientConfigured()) {
            $notes[] = 'Realtime screen pop may fall back to polling until Reverb client keys are configured.';
        }

        if (($warning = ReverbDeployment::hostMismatchWarning()) !== null) {
            $notes[] = $warning;
        }

        if (
            $this->webhookState() === 'waiting'
            && $this->credentials->twilioConfigured()
        ) {
            $notes[] = 'No inbound voice webhook has reached ARK yet. Confirm the Twilio voice URL matches the webhook below.';
        }

        if ($this->credentials->twilioConfigured() && ! $this->mobileVoiceClientConfigured()) {
            $notes[] = 'ARK Phone in-app calling needs a Twilio Voice API Key and TwiML App. Open Settings → Communications → Mobile, or run `php artisan ark:telephony:ensure-mobile-voice` on the server.';
        }

        return $notes;
    }

    public function formatTimestamp(?Carbon $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        $displayTimezone = \App\Ark\Operations\Settings\ShopDisplayTimezone::resolve();

        return $timestamp
            ->timezone($displayTimezone)
            ->format('M j, g:i A');
    }

    public function formatRelative(?Carbon $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return $timestamp->diffForHumans();
    }
}
