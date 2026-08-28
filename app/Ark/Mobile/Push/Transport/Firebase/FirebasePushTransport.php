<?php

namespace App\Ark\Mobile\Push\Transport\Firebase;

use App\Ark\Mobile\MobileDevice;
use App\Ark\Mobile\Push\MobilePushMessage;
use App\Ark\Mobile\Push\MobilePushSettings;
use App\Ark\Mobile\Push\PushTransport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FirebasePushTransport implements PushTransport
{
    public function __construct(
        private readonly FcmAccessTokenProvider $accessTokens,
    ) {}

    public function isAvailable(): bool
    {
        return $this->accessTokens->isConfigured();
    }

    public function send(string $deviceToken, MobilePushMessage $message): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $accessToken = $this->accessTokens->token();

        if ($accessToken === null) {
            return false;
        }

        $projectId = (string) (MobilePushSettings::current()->resolvedProjectId() ?? '');

        if ($projectId === '') {
            return false;
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $message->title,
                        'body' => $message->body,
                    ],
                    'data' => $message->dataPayload(),
                    'android' => $this->androidConfig($message),
                    'apns' => $this->apnsConfig($message),
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        Log::warning('Push transport delivery failed.', [
            'transport' => 'firebase',
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
            MobileDevice::query()
                ->where('fcm_token', $deviceToken)
                ->update(['fcm_token' => null]);
        }

        return false;
    }

    /**
     * Tone decides delivery loudness — urgent/waiting wake the device and play
     * sound; positive/info stay quiet.
     *
     * @return array<string, mixed>
     */
    private function androidConfig(MobilePushMessage $message): array
    {
        $config = [
            'priority' => $message->deliversImmediately() ? 'high' : 'normal',
        ];

        if (! $message->makesSound()) {
            $config['notification'] = ['notification_priority' => 'PRIORITY_LOW'];
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function apnsConfig(MobilePushMessage $message): array
    {
        $aps = $message->makesSound()
            ? [
                'sound' => 'default',
                'interruption-level' => 'time-sensitive',
                'content-available' => 1,
            ]
            : ['sound' => '', 'interruption-level' => 'passive'];

        return [
            'headers' => [
                'apns-priority' => $message->deliversImmediately() ? '10' : '5',
            ],
            'payload' => ['aps' => $aps],
        ];
    }
}
