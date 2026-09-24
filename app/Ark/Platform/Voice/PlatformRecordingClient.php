<?php

namespace App\Ark\Platform\Voice;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Signed installation requests for recordings Platform already owns.
 * Availability is presentation state. Playback always uses a separate GET.
 */
final class PlatformRecordingClient
{
    public const MAX_SIDS = 200;

    /** @var array<string, bool> */
    private array $known = [];

    public function ready(): bool
    {
        return PlatformConnection::current()->isConnected()
            && InstallationIdentity::read() !== null;
    }

    /**
     * @param  list<string>  $recordingSids
     */
    public function prime(array $recordingSids): void
    {
        $missing = [];
        foreach ($recordingSids as $recordingSid) {
            if (! $this->validSid($recordingSid) || array_key_exists($recordingSid, $this->known)) {
                continue;
            }
            $missing[$recordingSid] = $recordingSid;
        }

        if ($missing === []) {
            return;
        }

        $missing = array_values($missing);
        if (! $this->ready() || count($missing) > self::MAX_SIDS) {
            foreach ($missing as $recordingSid) {
                $this->known[$recordingSid] = false;
            }

            return;
        }

        $available = $this->requestAvailability($missing);
        if ($available === null) {
            foreach ($missing as $recordingSid) {
                $this->known[$recordingSid] = false;
            }

            return;
        }

        $owned = array_fill_keys($available, true);
        foreach ($missing as $recordingSid) {
            $this->known[$recordingSid] = isset($owned[$recordingSid]);
        }
    }

    public function owns(string $recordingSid): bool
    {
        if (! $this->validSid($recordingSid)) {
            return false;
        }

        if (! array_key_exists($recordingSid, $this->known)) {
            $this->prime([$recordingSid]);
        }

        return $this->known[$recordingSid] ?? false;
    }

    /**
     * @return array{bytes: string, content_type: string}|null
     */
    public function fetch(string $recordingSid): ?array
    {
        if (! $this->ready() || ! $this->validSid($recordingSid)) {
            return null;
        }

        $response = $this->send('GET', '/api/v1/services/voice/recordings/'.$recordingSid, '[]', 20);
        if ($response === null || ! $response->successful()) {
            return null;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0] ?? ''));
        $bytes = $response->body();
        if ($bytes === '' || ! str_starts_with($contentType, 'audio/')) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'content_type' => $contentType,
        ];
    }

    /**
     * @param  list<string>  $recordingSids
     * @return list<string>|null
     */
    private function requestAvailability(array $recordingSids): ?array
    {
        try {
            $raw = json_encode(array_values($recordingSids), JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $response = $this->send(
            'POST',
            '/api/v1/services/voice/recordings/availability',
            $raw,
            3,
        );
        if ($response === null || ! $response->successful()) {
            return null;
        }

        $json = $response->json();
        if (! is_array($json) || ($json['ok'] ?? false) !== true || ! is_array($json['available'] ?? null)) {
            return null;
        }

        $available = [];
        foreach ($json['available'] as $recordingSid) {
            if (is_string($recordingSid) && $this->validSid($recordingSid)) {
                $available[$recordingSid] = $recordingSid;
            }
        }

        return array_values($available);
    }

    private function send(string $method, string $path, string $raw, int $timeout): ?Response
    {
        $cloud = PlatformConnection::current();
        $credential = (string) $cloud->credential();
        $installationUuid = InstallationIdentity::read();
        if ($installationUuid === null || $credential === '') {
            return null;
        }

        $timestamp = (string) time();
        $nonce = Str::random(24);
        $signature = hash_hmac('sha256', implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            hash('sha256', $raw),
        ]), $credential);

        try {
            return Http::withHeaders([
                'Accept' => strtoupper($method) === 'GET' ? 'audio/mpeg' : 'application/json',
                'Content-Type' => 'application/json',
                'X-Ark-Installation-Id' => $installationUuid,
                'X-Ark-Timestamp' => $timestamp,
                'X-Ark-Nonce' => $nonce,
                'X-Ark-Signature' => $signature,
            ])->timeout($timeout)
                ->withBody($raw, 'application/json')
                ->send($method, $cloud->baseUrl().$path);
        } catch (\Throwable $e) {
            Log::warning('ark_platform.voice.recording_request_failed', [
                'path' => $path,
                'method' => $method,
                'error' => $e::class,
            ]);

            return null;
        }
    }

    private function validSid(string $recordingSid): bool
    {
        return preg_match('/^RE[a-fA-F0-9]{32}$/', $recordingSid) === 1;
    }
}
