<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyHealth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Provision Twilio Programmable Voice Client credentials for ARK Phone / Companion in-app calls.
 */
final class EnsureTwilioMobileVoiceCredentialsAction
{
    private const API_KEY_NAME = 'ARK Companion Voice';

    private const TWIML_APP_NAME = 'ARK Companion Voice';

    private const FCM_CREDENTIAL_NAME = 'ARK Mobile FCM';

    private const NOTIFY_CREDENTIALS_URL = 'https://notify.twilio.com/v1/Credentials';

    public function __construct(
        private readonly ShopIntegrationCredentials $integrations,
    ) {}

    /**
     * @return array{
     *     api_key_sid: ?string,
     *     api_key_secret: ?string,
     *     twiml_app_sid: ?string,
     *     fcm_credential_sid: ?string,
     *     created: list<string>,
     *     reused: list<string>,
     * }
     */
    public function execute(bool $force = false): array
    {
        if (! $this->integrations->twilioConfigured()) {
            throw new RuntimeException('Twilio account SID and auth token must be saved before provisioning mobile voice.');
        }

        $settings = ShopSettings::current();
        $accountSid = (string) $this->integrations->twilioAccountSid();
        $authToken = (string) $this->integrations->twilioAuthToken();
        $created = [];
        $reused = [];
        $updates = [];

        $apiKeySid = filled($settings->twilio_api_key_sid) ? (string) $settings->twilio_api_key_sid : null;
        $apiKeySecret = filled($settings->twilio_api_key_secret) ? (string) $settings->twilio_api_key_secret : null;

        if ($force || $apiKeySid === null || $apiKeySecret === null) {
            if (! $force && $apiKeySid !== null && $apiKeySecret === null) {
                $createdKey = $this->createApiKey($accountSid, $authToken);
                $apiKeySid = $createdKey['sid'];
                $apiKeySecret = $createdKey['secret'];
                $created[] = 'api_key';
            } else {
                $existingKey = $this->findApiKeyByName($accountSid, $authToken, self::API_KEY_NAME);

                if ($existingKey !== null && $apiKeySecret !== null && ! $force) {
                    $apiKeySid = $existingKey;
                    $reused[] = 'api_key';
                } else {
                    $createdKey = $this->createApiKey($accountSid, $authToken);
                    $apiKeySid = $createdKey['sid'];
                    $apiKeySecret = $createdKey['secret'];
                    $created[] = 'api_key';
                }
            }

            $updates['twilio_api_key_sid'] = $apiKeySid;
            $updates['twilio_api_key_secret'] = $apiKeySecret;
        } else {
            $reused[] = 'api_key';
        }

        $twimlAppSid = filled($settings->twilio_voice_twiml_app_sid)
            ? (string) $settings->twilio_voice_twiml_app_sid
            : null;
        $voiceUrl = TelephonyHealth::forCurrentShop()->clientOutboundWebhookUrl();

        if ($force || $twimlAppSid === null) {
            $existingApp = $this->findTwimlAppByName($accountSid, $authToken, self::TWIML_APP_NAME);

            if ($existingApp !== null) {
                $this->updateTwimlApp($accountSid, $authToken, $existingApp, $voiceUrl);
                $twimlAppSid = $existingApp;
                $reused[] = 'twiml_app';
            } else {
                $twimlAppSid = $this->createTwimlApp($accountSid, $authToken, $voiceUrl);
                $created[] = 'twiml_app';
            }

            $updates['twilio_voice_twiml_app_sid'] = $twimlAppSid;
        } else {
            $this->updateTwimlApp($accountSid, $authToken, $twimlAppSid, $voiceUrl);
            $reused[] = 'twiml_app';
        }

        $fcmCredentialSid = filled($settings->twilio_fcm_credential_sid)
            ? (string) $settings->twilio_fcm_credential_sid
            : null;

        if ($force || $fcmCredentialSid === null) {
            $serviceAccountPath = $this->resolveFirebaseServiceAccountPath();

            if ($serviceAccountPath !== null) {
                $existingFcm = $this->findCredentialByName($accountSid, $authToken, self::FCM_CREDENTIAL_NAME);

                if ($existingFcm !== null && ! $force) {
                    $fcmCredentialSid = $existingFcm;
                    $reused[] = 'fcm_credential';
                } else {
                    try {
                        $fcmCredentialSid = $this->createFcmCredential(
                            $accountSid,
                            $authToken,
                            $serviceAccountPath,
                        );
                        $created[] = 'fcm_credential';
                    } catch (\Throwable $exception) {
                        Log::warning('twilio.mobile_voice.fcm_credential_failed', [
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }

                if ($fcmCredentialSid !== null) {
                    $updates['twilio_fcm_credential_sid'] = $fcmCredentialSid;
                }
            }
        } else {
            $reused[] = 'fcm_credential';
        }

        if ($updates !== []) {
            $settings->persistTrusted($updates);
        }

        return [
            'api_key_sid' => $apiKeySid,
            'api_key_secret' => $apiKeySecret !== null ? '[saved]' : null,
            'twiml_app_sid' => $twimlAppSid,
            'fcm_credential_sid' => $fcmCredentialSid,
            'created' => $created,
            'reused' => $reused,
        ];
    }

    private function http(string $accountSid, string $authToken)
    {
        return Http::withBasicAuth($accountSid, $authToken)
            ->acceptJson()
            ->asForm();
    }

    private function findApiKeyByName(string $accountSid, string $authToken, string $name): ?string
    {
        $response = $this->http($accountSid, $authToken)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Keys.json");

        if (! $response->successful()) {
            Log::warning('twilio.mobile_voice.list_keys_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        foreach ($response->json('keys') ?? [] as $key) {
            if (($key['friendly_name'] ?? '') === $name && filled($key['sid'] ?? null)) {
                return (string) $key['sid'];
            }
        }

        return null;
    }

    /**
     * @return array{sid: string, secret: string}
     */
    private function createApiKey(string $accountSid, string $authToken): array
    {
        $response = $this->http($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Keys.json", [
                'FriendlyName' => self::API_KEY_NAME,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio API key creation failed: '.$response->body());
        }

        $sid = trim((string) ($response->json('sid') ?? ''));
        $secret = trim((string) ($response->json('secret') ?? ''));

        if ($sid === '' || $secret === '') {
            throw new RuntimeException('Twilio API key creation returned an incomplete payload.');
        }

        return ['sid' => $sid, 'secret' => $secret];
    }

    private function findTwimlAppByName(string $accountSid, string $authToken, string $name): ?string
    {
        $response = $this->http($accountSid, $authToken)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Applications.json");

        if (! $response->successful()) {
            Log::warning('twilio.mobile_voice.list_apps_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        foreach ($response->json('applications') ?? [] as $application) {
            if (($application['friendly_name'] ?? '') === $name && filled($application['sid'] ?? null)) {
                return (string) $application['sid'];
            }
        }

        return null;
    }

    private function createTwimlApp(string $accountSid, string $authToken, string $voiceUrl): string
    {
        $response = $this->http($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Applications.json", [
                'FriendlyName' => self::TWIML_APP_NAME,
                'VoiceUrl' => $voiceUrl,
                'VoiceMethod' => 'POST',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio TwiML App creation failed: '.$response->body());
        }

        $sid = trim((string) ($response->json('sid') ?? ''));

        if ($sid === '') {
            throw new RuntimeException('Twilio TwiML App creation returned no SID.');
        }

        return $sid;
    }

    private function updateTwimlApp(string $accountSid, string $authToken, string $appSid, string $voiceUrl): void
    {
        $response = $this->http($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Applications/{$appSid}.json", [
                'VoiceUrl' => $voiceUrl,
                'VoiceMethod' => 'POST',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio TwiML App update failed: '.$response->body());
        }
    }

    private function findCredentialByName(string $accountSid, string $authToken, string $name): ?string
    {
        $response = $this->http($accountSid, $authToken)
            ->get(self::NOTIFY_CREDENTIALS_URL);

        if (! $response->successful()) {
            Log::warning('twilio.mobile_voice.list_credentials_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        foreach ($response->json('credentials') ?? [] as $credential) {
            if (($credential['friendly_name'] ?? '') === $name && filled($credential['sid'] ?? null)) {
                return (string) $credential['sid'];
            }
        }

        return null;
    }

    private function createFcmCredential(string $accountSid, string $authToken, string $serviceAccountPath): string
    {
        $secret = file_get_contents($serviceAccountPath);

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Firebase service account file is empty.');
        }

        $response = $this->http($accountSid, $authToken)
            ->post(self::NOTIFY_CREDENTIALS_URL, [
                'Type' => 'fcm',
                'FriendlyName' => self::FCM_CREDENTIAL_NAME,
                'Secret' => $secret,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio FCM credential creation failed: '.$response->body());
        }

        $sid = trim((string) ($response->json('sid') ?? ''));

        if ($sid === '') {
            throw new RuntimeException('Twilio FCM credential creation returned no SID.');
        }

        return $sid;
    }

    private function resolveFirebaseServiceAccountPath(): ?string
    {
        $configured = trim((string) config('firebase.credentials.file', ''));

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            storage_path('app/private/firebase-mobile-service-account.json'),
            (string) config('services.firebase.credentials'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        $envPath = trim((string) env('FIREBASE_CREDENTIALS', ''));

        if ($envPath !== '' && is_file($envPath)) {
            return $envPath;
        }

        return null;
    }
}
